<?php
// app/Models/TransferModel.php

class TransferModel extends BaseModel {
    protected string $table = 'transfers';
    private const STATUS_PENDING = 'PENDING';
    private const STATUS_COUNTERED = 'COUNTERED';
    private const STATUS_COMPLETED = 'COMPLETED';
    private const STATUS_CANCELLED = 'CANCELLED';
    private const STATUS_REJECTED = 'REJECTED';
    private const STATUS_SUPERSEDED = 'SUPERSEDED';

    public function __construct() {
        parent::__construct();
        if ($this->db->shouldRunRuntimeDdlFallback()) {
            $this->ensureTransferMarketColumns();
        }
    }

    public function getActive(): array {
        return $this->db->fetchAll(
            "SELECT t.*, CONCAT(p.first_name, ' ', p.last_name) AS player_name, p.position, p.overall,
                    fc.name AS from_club_name, tc.name AS to_club_name
             FROM transfers t
             JOIN players p ON t.player_id = p.id
             LEFT JOIN clubs fc ON t.from_club_id = fc.id
             LEFT JOIN clubs tc ON t.to_club_id = tc.id
             WHERE t.status IN ('PENDING', 'COUNTERED')
             ORDER BY t.created_at DESC"
        );
    }

    public function getIncomingOffers(int $clubId): array {
        return $this->db->fetchAll(
            "SELECT t.*, CONCAT(p.first_name, ' ', p.last_name) AS player_name, p.position, p.overall, p.market_value, p.asking_price,
                    p.squad_role, p.morale_score, p.last_minutes_played, p.wage, p.potential,
                    fc.name AS from_club_name, tc.name AS to_club_name
             FROM transfers t
             JOIN players p ON t.player_id = p.id
             LEFT JOIN clubs fc ON t.from_club_id = fc.id
             LEFT JOIN clubs tc ON t.to_club_id = tc.id
             WHERE t.from_club_id = ? AND t.status IN ('PENDING', 'COUNTERED')
             ORDER BY t.created_at DESC",
            [$clubId]
        );
    }

    public function getByClub(int $clubId): array {
        return $this->db->fetchAll(
            "SELECT t.*, CONCAT(p.first_name, ' ', p.last_name) AS player_name, p.position, p.overall, p.market_value
             FROM transfers t
             JOIN players p ON t.player_id = p.id
             WHERE (t.from_club_id = ? OR t.to_club_id = ?)
             ORDER BY t.created_at DESC",
            [$clubId, $clubId]
        );
    }

    public function makeBid(int $playerId, int $fromClubId, int $toClubId, float $amount): int {
        if ($fromClubId === $toClubId) {
            return 0;
        }
        $dup = $this->db->fetchOne(
            "SELECT id FROM transfers
             WHERE player_id = ? AND from_club_id = ? AND to_club_id = ? AND status IN ('PENDING', 'COUNTERED')
             LIMIT 1",
            [$playerId, $fromClubId, $toClubId]
        );
        if ($dup) {
            return (int)$dup['id'];
        }

        $this->db->execute(
            "UPDATE transfers
             SET status = 'SUPERSEDED', responded_at = NOW()
             WHERE player_id = ? AND to_club_id = ? AND status IN ('PENDING','COUNTERED')",
            [$playerId, $toClubId]
        );

        return $this->create([
            'player_id' => $playerId,
            'from_club_id' => $fromClubId,
            'to_club_id' => $toClubId,
            'type' => 'PERMANENT',
            'fee' => $amount,
            'status' => self::STATUS_PENDING,
            'counter_fee' => null,
            'negotiation_round' => 0,
            'initiated_by' => Auth::id() ?? 1,
            'season_id' => $this->resolveCurrentSeasonId(),
            'created_at' => date('Y-m-d H:i:s'),
            'responded_at' => null,
        ]);
    }

    public function counter(int $transferId, int $sellerClubId, int $counterFee): bool {
        if ($counterFee <= 0) return false;

        $this->db->beginTransaction();
        try {
            $transfer = $this->db->fetchOne("SELECT * FROM transfers WHERE id = ? FOR UPDATE", [$transferId]);
            if (!$transfer || (int)$transfer['from_club_id'] !== $sellerClubId) {
                $this->db->rollBack();
                return false;
            }
            if (($transfer['status'] ?? '') !== self::STATUS_PENDING) {
                $this->db->rollBack();
                return false;
            }

            $this->db->execute(
                "UPDATE transfers
                 SET status = 'COUNTERED',
                     counter_fee = ?,
                     negotiation_round = 1,
                     responded_at = NOW(),
                     countered_at = NOW()
                 WHERE id = ? AND status = 'PENDING'",
                [$counterFee, $transferId]
            );
            $this->db->commit();
            return true;
        } catch (Throwable $e) {
            $this->db->rollBack();
            return false;
        }
    }

    public function accept(int $transferId): bool {
        $finance = new FinanceService($this->db);
        $this->db->beginTransaction();
        try {
            $transfer = $this->db->fetchOne(
                "SELECT * FROM transfers WHERE id = ? FOR UPDATE",
                [$transferId]
            );

            if (!$transfer || !in_array((string)$transfer['status'], [self::STATUS_PENDING, self::STATUS_COUNTERED], true)) {
                $this->db->rollBack();
                return false;
            }

            $player = $this->db->fetchOne("SELECT id, club_id, is_transfer_listed FROM players WHERE id = ? FOR UPDATE", [(int)$transfer['player_id']]);
            if (!$player || (int)($player['club_id'] ?? 0) !== (int)($transfer['from_club_id'] ?? 0)) {
                $this->db->rollBack();
                return false;
            }

            $finalFee = (int)($transfer['status'] === self::STATUS_COUNTERED ? ($transfer['counter_fee'] ?? 0) : ($transfer['fee'] ?? 0));
            if ($finalFee <= 0) {
                $this->db->rollBack();
                return false;
            }

            $buyer = $this->db->fetchOne("SELECT id, balance FROM clubs WHERE id = ? FOR UPDATE", [(int)$transfer['to_club_id']]);
            if (!$buyer || (int)($buyer['balance'] ?? 0) < $finalFee) {
                $this->db->rollBack();
                return false;
            }

            $this->db->query(
                "UPDATE transfers
                 SET status = 'COMPLETED', completed_at = ?, fee = ?, responded_at = NOW()
                 WHERE id = ? AND status IN ('PENDING', 'COUNTERED')",
                [date('Y-m-d H:i:s'), $finalFee, $transferId]
            );

            $this->db->query("UPDATE players SET club_id = ?, is_transfer_listed = 0, transfer_listed_at = NULL, asking_price = NULL WHERE id = ?", [$transfer['to_club_id'], $transfer['player_id']]);

            $outPost = $finance->postEntry((int)$transfer['to_club_id'], 'TRANSFER_OUT', -1 * $finalFee, 'Transfer fee paid', null, 'TRANSFER', $transferId, [], false);
            if (empty($outPost['ok'])) {
                $this->db->rollBack();
                return false;
            }

            if (!empty($transfer['from_club_id'])) {
                $inPost = $finance->postEntry((int)$transfer['from_club_id'], 'TRANSFER_IN', $finalFee, 'Transfer fee received', null, 'TRANSFER', $transferId, [], false);
                if (empty($inPost['ok'])) {
                    $this->db->rollBack();
                    return false;
                }
            }

            $this->db->execute(
                "UPDATE transfers
                 SET status = 'CANCELLED', responded_at = NOW()
                 WHERE player_id = ? AND id <> ? AND status IN ('PENDING', 'COUNTERED')",
                [(int)$transfer['player_id'], $transferId]
            );

            $this->db->commit();
            return true;
        } catch (Throwable $e) {
            $this->db->rollBack();
            return false;
        }
    }

    public function reject(int $transferId): bool {
        return $this->db->execute(
            "UPDATE transfers
             SET status = 'REJECTED', responded_at = NOW()
             WHERE id = ? AND status IN ('PENDING','COUNTERED')",
            [$transferId]
        ) > 0;
    }

    public function getDispositionForPlayer(array $player): array {
        $role = strtoupper((string)($player['squad_role'] ?? 'ROTATION'));
        $moraleScore = (int)($player['morale_score'] ?? (int)round(((float)($player['morale'] ?? 7.0)) * 10));
        $lastMinutes = (int)($player['last_minutes_played'] ?? 0);
        $potentialGap = max(0, (int)($player['potential'] ?? 0) - (int)($player['overall'] ?? 0));

        $willingness = 50;
        if (in_array($role, ['KEY_PLAYER', 'REGULAR_STARTER'], true)) $willingness -= 8;
        if (in_array($role, ['BENCH', 'PROSPECT'], true)) $willingness += 6;
        if ($moraleScore < 55) $willingness += 10;
        if ($moraleScore > 78) $willingness -= 6;
        if ($lastMinutes <= 10) $willingness += 7;
        if ($potentialGap >= 8) $willingness -= 4;

        $willingness = max(10, min(90, $willingness));
        return [
            'willingness_score' => $willingness,
            'label' => $willingness >= 65 ? 'Open' : ($willingness >= 45 ? 'Neutral' : 'Reluctant'),
        ];
    }

    public function buildPricingContext(array $player): array {
        $marketValue = max(1, (int)($player['market_value'] ?? 1));
        $wage = max(0, (int)($player['wage'] ?? 0));
        $role = strtoupper((string)($player['squad_role'] ?? 'ROTATION'));
        $moraleScore = (int)($player['morale_score'] ?? (int)round(((float)($player['morale'] ?? 7.0)) * 10));
        $lastMinutes = (int)($player['last_minutes_played'] ?? 0);
        $potentialGap = max(0, (int)($player['potential'] ?? 0) - (int)($player['overall'] ?? 0));

        $pressure = 1.0;
        if ($wage > 30000) $pressure -= 0.04;
        if ($moraleScore < 55) $pressure -= 0.05;
        if ($lastMinutes <= 10) $pressure -= 0.04;
        if (in_array($role, ['KEY_PLAYER', 'REGULAR_STARTER'], true)) $pressure += 0.08;
        if ($potentialGap >= 8) $pressure += 0.06;
        $pressure = max(0.78, min(1.28, $pressure));

        $minAccept = (int)round($marketValue * (0.86 * $pressure));
        $counterTarget = (int)round($marketValue * (1.00 * $pressure));
        $maxReasonable = (int)round($marketValue * (1.65 * $pressure));

        return [
            'market_value' => $marketValue,
            'pressure_factor' => $pressure,
            'min_accept' => max(1, $minAccept),
            'counter_target' => max(1, $counterTarget),
            'max_reasonable' => max(1, $maxReasonable),
        ];
    }

    public function determineSellerDecision(array $transfer, array $player): array {
        $pricing = $this->buildPricingContext($player);
        $fee = (int)($transfer['fee'] ?? 0);
        if ($fee >= $pricing['counter_target']) {
            return ['action' => 'accept', 'pricing' => $pricing];
        }
        if ($fee >= $pricing['min_accept']) {
            return ['action' => 'counter', 'counter_fee' => $pricing['counter_target'], 'pricing' => $pricing];
        }
        return ['action' => 'reject', 'pricing' => $pricing];
    }

    public function setTransferListed(int $playerId, int $clubId, bool $listed, ?int $askingPrice = null): bool {
        $player = $this->db->fetchOne("SELECT id, club_id FROM players WHERE id = ?", [$playerId]);
        if (!$player || (int)$player['club_id'] !== $clubId) {
            return false;
        }
        return $this->db->execute(
            "UPDATE players
             SET is_transfer_listed = ?, asking_price = ?, transfer_listed_at = ?
             WHERE id = ?",
            [
                $listed ? 1 : 0,
                $listed ? max(1, (int)$askingPrice) : null,
                $listed ? date('Y-m-d H:i:s') : null,
                $playerId
            ]
        ) > 0;
    }

    private function resolveCurrentSeasonId(): ?int {
        $row = $this->db->fetchOne("SELECT id FROM seasons WHERE status = 'ACTIVE' ORDER BY id DESC LIMIT 1");
        return $row ? (int)$row['id'] : null;
    }

    private function ensureTransferMarketColumns(): void {
        $hasListed = $this->db->fetchOne(
            "SELECT COLUMN_NAME FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'players' AND COLUMN_NAME = 'is_transfer_listed'"
        );
        if (!$hasListed) {
            $this->db->execute("ALTER TABLE players ADD COLUMN is_transfer_listed BOOLEAN DEFAULT 0 AFTER market_value");
        }

        $hasAsking = $this->db->fetchOne(
            "SELECT COLUMN_NAME FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'players' AND COLUMN_NAME = 'asking_price'"
        );
        if (!$hasAsking) {
            $this->db->execute("ALTER TABLE players ADD COLUMN asking_price BIGINT NULL AFTER is_transfer_listed");
        }

        $hasListedAt = $this->db->fetchOne(
            "SELECT COLUMN_NAME FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'players' AND COLUMN_NAME = 'transfer_listed_at'"
        );
        if (!$hasListedAt) {
            $this->db->execute("ALTER TABLE players ADD COLUMN transfer_listed_at DATETIME NULL AFTER asking_price");
        }

        $hasSeasonId = $this->db->fetchOne(
            "SELECT COLUMN_NAME FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'transfers' AND COLUMN_NAME = 'season_id'"
        );
        if (!$hasSeasonId) {
            $this->db->execute("ALTER TABLE transfers ADD COLUMN season_id INT NULL AFTER initiated_by");
        }

        $hasCounterFee = $this->db->fetchOne(
            "SELECT COLUMN_NAME FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'transfers' AND COLUMN_NAME = 'counter_fee'"
        );
        if (!$hasCounterFee) {
            $this->db->execute("ALTER TABLE transfers ADD COLUMN counter_fee BIGINT NULL AFTER fee");
        }

        $hasRound = $this->db->fetchOne(
            "SELECT COLUMN_NAME FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'transfers' AND COLUMN_NAME = 'negotiation_round'"
        );
        if (!$hasRound) {
            $this->db->execute("ALTER TABLE transfers ADD COLUMN negotiation_round TINYINT DEFAULT 0 AFTER counter_fee");
        }

        $hasCounteredAt = $this->db->fetchOne(
            "SELECT COLUMN_NAME FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'transfers' AND COLUMN_NAME = 'countered_at'"
        );
        if (!$hasCounteredAt) {
            $this->db->execute("ALTER TABLE transfers ADD COLUMN countered_at DATETIME NULL AFTER completed_at");
        }

        $hasRespondedAt = $this->db->fetchOne(
            "SELECT COLUMN_NAME FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'transfers' AND COLUMN_NAME = 'responded_at'"
        );
        if (!$hasRespondedAt) {
            $this->db->execute("ALTER TABLE transfers ADD COLUMN responded_at DATETIME NULL AFTER countered_at");
        }

        $this->db->execute(
            "ALTER TABLE transfers
             MODIFY COLUMN status ENUM('PENDING','COUNTERED','COMPLETED','CANCELLED','REJECTED','SUPERSEDED') DEFAULT 'PENDING'"
        );
    }
}
