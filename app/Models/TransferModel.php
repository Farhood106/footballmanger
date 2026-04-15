<?php
// app/Models/TransferModel.php

class TransferModel extends BaseModel {
    protected string $table = 'transfers';

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
             WHERE t.status = 'PENDING'
             ORDER BY t.created_at DESC"
        );
    }

    public function getIncomingOffers(int $clubId): array {
        return $this->db->fetchAll(
            "SELECT t.*, CONCAT(p.first_name, ' ', p.last_name) AS player_name, p.position, p.overall, p.market_value,
                    fc.name AS from_club_name, tc.name AS to_club_name
             FROM transfers t
             JOIN players p ON t.player_id = p.id
             LEFT JOIN clubs fc ON t.from_club_id = fc.id
             LEFT JOIN clubs tc ON t.to_club_id = tc.id
             WHERE t.from_club_id = ? AND t.status = 'PENDING'
             ORDER BY t.created_at DESC",
            [$clubId]
        );
    }

    public function getByClub(int $clubId): array {
        return $this->db->fetchAll(
            "SELECT t.*, CONCAT(p.first_name, ' ', p.last_name) AS player_name, p.position, p.overall
             FROM transfers t
             JOIN players p ON t.player_id = p.id
             WHERE (t.from_club_id = ? OR t.to_club_id = ?)
             ORDER BY t.created_at DESC",
            [$clubId, $clubId]
        );
    }

    public function makeBid(int $playerId, int $fromClubId, int $toClubId, float $amount): int {
        $dup = $this->db->fetchOne(
            "SELECT id FROM transfers
             WHERE player_id = ? AND from_club_id = ? AND to_club_id = ? AND status = 'PENDING'
             LIMIT 1",
            [$playerId, $fromClubId, $toClubId]
        );
        if ($dup) {
            return (int)$dup['id'];
        }

        return $this->create([
            'player_id' => $playerId,
            'from_club_id' => $fromClubId,
            'to_club_id' => $toClubId,
            'type' => 'PERMANENT',
            'fee' => $amount,
            'status' => 'PENDING',
            'initiated_by' => Auth::id() ?? 1,
            'season_id' => $this->resolveCurrentSeasonId(),
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }

    public function accept(int $transferId): bool {
        $finance = new FinanceService($this->db);
        $this->db->beginTransaction();
        try {
            $transfer = $this->db->fetchOne(
                "SELECT * FROM transfers WHERE id = ? FOR UPDATE",
                [$transferId]
            );

            if (!$transfer || $transfer['status'] !== 'PENDING') {
                $this->db->rollBack();
                return false;
            }

            $player = $this->db->fetchOne("SELECT id, club_id, is_transfer_listed FROM players WHERE id = ? FOR UPDATE", [(int)$transfer['player_id']]);
            if (!$player || (int)($player['club_id'] ?? 0) !== (int)($transfer['from_club_id'] ?? 0)) {
                $this->db->rollBack();
                return false;
            }

            $buyer = $this->db->fetchOne("SELECT id, balance FROM clubs WHERE id = ? FOR UPDATE", [(int)$transfer['to_club_id']]);
            if (!$buyer || (int)($buyer['balance'] ?? 0) < (int)$transfer['fee']) {
                $this->db->rollBack();
                return false;
            }

            $this->db->query(
                "UPDATE transfers SET status = 'COMPLETED', completed_at = ? WHERE id = ? AND status = 'PENDING'",
                [date('Y-m-d H:i:s'), $transferId]
            );

            $this->db->query("UPDATE players SET club_id = ?, is_transfer_listed = 0, transfer_listed_at = NULL, asking_price = NULL WHERE id = ?", [$transfer['to_club_id'], $transfer['player_id']]);

            $outPost = $finance->postEntry((int)$transfer['to_club_id'], 'TRANSFER_OUT', -1 * (int)$transfer['fee'], 'Transfer fee paid', null, 'TRANSFER', $transferId, [], false);
            if (empty($outPost['ok'])) {
                $this->db->rollBack();
                return false;
            }

            if (!empty($transfer['from_club_id'])) {
                $inPost = $finance->postEntry((int)$transfer['from_club_id'], 'TRANSFER_IN', (int)$transfer['fee'], 'Transfer fee received', null, 'TRANSFER', $transferId, [], false);
                if (empty($inPost['ok'])) {
                    $this->db->rollBack();
                    return false;
                }
            }

            $this->db->execute(
                "UPDATE transfers
                 SET status = 'CANCELLED'
                 WHERE player_id = ? AND id <> ? AND status = 'PENDING'",
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
        return $this->update($transferId, ['status' => 'REJECTED']);
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
    }
}
