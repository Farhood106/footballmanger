<?php
// app/Services/FinanceService.php

class FinanceService {
    private Database $db;

    private const ALLOWED_ENTRY_TYPES = [
        'COACH_SALARY',
        'PLAYER_WAGE',
        'MATCH_REWARD',
        'SEASON_REWARD',
        'GOVERNANCE_PENALTY',
        'GOVERNANCE_COMPENSATION',
        'TRANSFER_IN',
        'TRANSFER_OUT',
        'OWNER_FUNDING',
        'SPONSOR_INCOME',
        'OPERATING_COST',
        'MANUAL_ADMIN_ADJUSTMENT',
        'FACILITY_UPGRADE',
        'FACILITY_DOWNGRADE_REFUND',
        'FACILITY_MAINTENANCE',
        // backward compatibility:
        'WAGE', 'STAFF_WAGE', 'PENALTY', 'PRIZE', 'OTHER', 'SPONSOR', 'TICKET'
    ];

    public function __construct(?Database $db = null) {
        $this->db = $db ?? Database::getInstance();
        $this->ensureFinanceTables();
    }

    public function postEntry(
        int $clubId,
        string $entryType,
        int $amount,
        string $description,
        ?int $seasonId = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
        array $meta = [],
        bool $manageTransaction = true
    ): array {
        if ($clubId <= 0) return ['ok' => false, 'error' => 'Invalid club id.'];
        if (!in_array($entryType, self::ALLOWED_ENTRY_TYPES, true)) {
            return ['ok' => false, 'error' => 'Unsupported finance entry type.'];
        }

        $startedTx = false;
        if ($manageTransaction && !$this->db->inTransaction()) {
            $this->db->beginTransaction();
            $startedTx = true;
        }
        try {
            if ($referenceType !== null && $referenceId !== null) {
                $dup = $this->db->fetchOne(
                    "SELECT id FROM club_finance_ledger
                     WHERE club_id = ? AND entry_type = ? AND reference_type = ? AND reference_id = ?
                     LIMIT 1",
                    [$clubId, $entryType, $referenceType, $referenceId]
                );
                if ($dup) {
                    if ($startedTx) {
                        $this->db->rollBack();
                    }
                    return ['ok' => false, 'error' => 'Duplicate finance posting blocked.'];
                }
            }

            $this->db->execute("UPDATE clubs SET balance = balance + ? WHERE id = ?", [$amount, $clubId]);
            $this->db->insert('club_finance_ledger', [
                'club_id' => $clubId,
                'season_id' => $seasonId,
                'entry_type' => $entryType,
                'amount' => $amount,
                'description' => $description,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'meta_json' => empty($meta) ? null : json_encode($meta, JSON_UNESCAPED_UNICODE),
            ]);

            if ($startedTx) {
                $this->db->commit();
            }
            return ['ok' => true];
        } catch (Throwable $e) {
            if ($startedTx) {
                $this->db->rollBack();
            }
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    public function postCoachSalariesForCycle(string $cycleDate): array {
        $contracts = $this->db->fetchAll(
            "SELECT mc.*, c.name AS club_name
             FROM manager_contracts mc
             JOIN clubs c ON c.id = mc.club_id
             WHERE mc.status = 'ACTIVE'"
        );

        $posted = 0;
        foreach ($contracts as $contract) {
            $clubId = (int)$contract['club_id'];
            $contractId = (int)$contract['id'];
            $salary = (int)($contract['salary'] ?? 0);
            if ($salary <= 0) continue;

            $cycleRef = abs(crc32($contractId . ':' . $cycleDate));
            $result = $this->postEntry(
                $clubId,
                'COACH_SALARY',
                -1 * $salary,
                'Coach salary posted for cycle ' . $cycleDate,
                null,
                'CONTRACT_SALARY_CYCLE',
                $cycleRef,
                ['cycle_date' => $cycleDate, 'contract_id' => $contractId]
            );
            if (!empty($result['ok'])) $posted++;
        }

        return ['ok' => true, 'posted' => $posted];
    }

    public function postPlayerWagesForCycle(string $cycleDate): array {
        $players = $this->db->fetchAll(
            "SELECT id, club_id, overall, wage, contract_end
             FROM players
             WHERE club_id IS NOT NULL
               AND is_retired = 0
               AND (contract_end IS NULL OR contract_end >= ?)
             ORDER BY club_id ASC, id ASC",
            [$cycleDate]
        );

        $posted = 0;
        $duplicates = 0;
        $insufficient = 0;
        foreach ($players as $player) {
            $clubId = (int)($player['club_id'] ?? 0);
            $playerId = (int)$player['id'];
            if ($clubId <= 0 || $playerId <= 0) continue;

            $wage = (int)($player['wage'] ?? 0);
            if ($wage <= 0) {
                $wage = $this->estimatePlayerWage((int)($player['overall'] ?? 50));
            }
            if ($wage <= 0) continue;

            if (!$this->canClubAffordExpense($clubId, $wage)) {
                $insufficient++;
                continue;
            }

            $cycleRef = abs(crc32($playerId . ':' . $clubId . ':' . $cycleDate));
            $result = $this->postEntry(
                $clubId,
                'PLAYER_WAGE',
                -1 * $wage,
                'Player wage posted for cycle ' . $cycleDate,
                null,
                'PLAYER_WAGE_CYCLE',
                $cycleRef,
                ['cycle_date' => $cycleDate, 'player_id' => $playerId, 'wage' => $wage]
            );
            if (!empty($result['ok'])) {
                $posted++;
            } elseif (($result['error'] ?? '') === 'Duplicate finance posting blocked.') {
                $duplicates++;
            }
        }

        return ['ok' => true, 'posted' => $posted, 'duplicates' => $duplicates, 'insufficient_balance' => $insufficient];
    }

    public function postRecurringSponsorPayoutsForCycle(string $cycleDate): array {
        $sponsors = $this->db->fetchAll(
            "SELECT id, club_id, tier, brand_name, is_active, recurring_amount, recurring_cycle_days, last_paid_at
             FROM club_sponsors
             WHERE is_active = 1
             ORDER BY club_id ASC, id ASC"
        );

        $posted = 0;
        $duplicates = 0;
        foreach ($sponsors as $sponsor) {
            $clubId = (int)$sponsor['club_id'];
            $sponsorId = (int)$sponsor['id'];
            if ($clubId <= 0 || $sponsorId <= 0) continue;

            $cycleDays = max(1, (int)($sponsor['recurring_cycle_days'] ?? 7));
            $lastPaidAt = !empty($sponsor['last_paid_at']) ? (string)$sponsor['last_paid_at'] : null;
            if ($lastPaidAt !== null) {
                $daysSinceLast = (int)floor((strtotime($cycleDate . ' 00:00:00') - strtotime(substr($lastPaidAt, 0, 10) . ' 00:00:00')) / 86400);
                if ($daysSinceLast < $cycleDays) {
                    continue;
                }
            }

            $amount = (int)($sponsor['recurring_amount'] ?? 0);
            if ($amount <= 0) {
                $amount = $this->defaultSponsorRecurringAmount((string)($sponsor['tier'] ?? 'minor'));
            }
            if ($amount <= 0) continue;

            $referenceId = abs(crc32('REC_SPN:' . $clubId . ':' . $sponsorId . ':' . $cycleDate));
            $result = $this->postEntry(
                $clubId,
                'SPONSOR_INCOME',
                $amount,
                'Recurring sponsor payout: ' . (string)($sponsor['brand_name'] ?? 'sponsor'),
                null,
                'SPONSOR_RECURRING_CYCLE',
                $referenceId,
                ['cycle_date' => $cycleDate, 'sponsor_id' => $sponsorId, 'tier' => $sponsor['tier'] ?? 'minor', 'recurring' => true]
            );
            if (!empty($result['ok'])) {
                $this->db->execute("UPDATE club_sponsors SET last_paid_at = ? WHERE id = ?", [$cycleDate . ' 00:00:00', $sponsorId]);
                $posted++;
            } elseif (($result['error'] ?? '') === 'Duplicate finance posting blocked.') {
                $duplicates++;
            }
        }

        return ['ok' => true, 'posted' => $posted, 'duplicates' => $duplicates];
    }

    public function postOperatingCostsForCycle(string $cycleDate): array {
        $clubs = $this->db->fetchAll(
            "SELECT c.id, c.reputation,
                    COALESCE(p.players_count, 0) AS players_count,
                    COALESCE(f.stadium_level, 1) AS stadium_level,
                    COALESCE(f.hq_level, 1) AS hq_level
             FROM clubs c
             LEFT JOIN (
                SELECT club_id, COUNT(*) AS players_count
                FROM players
                WHERE is_retired = 0
                GROUP BY club_id
             ) p ON p.club_id = c.id
             LEFT JOIN (
                SELECT club_id,
                       MAX(CASE WHEN facility_type = 'stadium' THEN level ELSE 1 END) AS stadium_level,
                       MAX(CASE WHEN facility_type = 'headquarters' THEN level ELSE 1 END) AS hq_level
                FROM club_facilities
                GROUP BY club_id
             ) f ON f.club_id = c.id
             ORDER BY c.id ASC"
        );

        $posted = 0;
        $duplicates = 0;
        $insufficient = 0;
        foreach ($clubs as $club) {
            $clubId = (int)$club['id'];
            $cost = $this->calculateOperatingCost(
                (int)($club['reputation'] ?? 0),
                (int)($club['players_count'] ?? 0),
                (int)($club['stadium_level'] ?? 1),
                (int)($club['hq_level'] ?? 1)
            );
            if ($clubId <= 0 || $cost <= 0) continue;
            if (!$this->canClubAffordExpense($clubId, $cost)) {
                $insufficient++;
                continue;
            }

            $referenceId = abs(crc32('OPERATING:' . $clubId . ':' . $cycleDate));
            $result = $this->postEntry(
                $clubId,
                'OPERATING_COST',
                -1 * $cost,
                'Recurring club operating cost for cycle ' . $cycleDate,
                null,
                'OPERATING_COST_DAILY',
                $referenceId,
                ['cycle_date' => $cycleDate, 'players_count' => (int)$club['players_count']]
            );
            if (!empty($result['ok'])) {
                $posted++;
            } elseif (($result['error'] ?? '') === 'Duplicate finance posting blocked.') {
                $duplicates++;
            }
        }

        return ['ok' => true, 'posted' => $posted, 'duplicates' => $duplicates, 'insufficient_balance' => $insufficient];
    }

    public function postOwnerFunding(int $clubId, int $ownerUserId, int $amount, string $note = '', ?string $externalRef = null): array {
        if ($amount <= 0) {
            return ['ok' => false, 'error' => 'Funding amount must be positive.'];
        }
        $externalRef = trim((string)$externalRef) ?: null;

        $club = $this->db->fetchOne("SELECT owner_user_id FROM clubs WHERE id = ?", [$clubId]);
        if (!$club) return ['ok' => false, 'error' => 'Club not found.'];
        if ((int)($club['owner_user_id'] ?? 0) !== $ownerUserId) {
            return ['ok' => false, 'error' => 'Only club owner can fund this club.'];
        }

        if ($externalRef !== null) {
            $dup = $this->db->fetchOne(
                "SELECT id FROM club_owner_funding_events
                 WHERE club_id = ? AND external_reference = ? AND status = 'posted'
                 LIMIT 1",
                [$clubId, $externalRef]
            );
            if ($dup) {
                return ['ok' => false, 'error' => 'Funding reference already posted.'];
            }
        }

        $eventId = (int)$this->db->insert('club_owner_funding_events', [
            'club_id' => $clubId,
            'owner_user_id' => $ownerUserId,
            'amount' => $amount,
            'note' => trim($note) ?: null,
            'external_reference' => $externalRef,
            'status' => 'posted',
        ]);

        return $this->postEntry(
            $clubId,
            'OWNER_FUNDING',
            $amount,
            'Owner funding posted',
            null,
            'OWNER_FUNDING_EVENT',
            $eventId,
            ['owner_user_id' => $ownerUserId]
        );
    }

    public function postSponsorIncome(int $clubId, int $sponsorId, int $amount, string $note = ''): array {
        if ($amount <= 0) return ['ok' => false, 'error' => 'Sponsor income must be positive.'];
        $sponsor = $this->db->fetchOne("SELECT * FROM club_sponsors WHERE id = ? AND club_id = ?", [$sponsorId, $clubId]);
        if (!$sponsor) return ['ok' => false, 'error' => 'Sponsor not found for club.'];
        if ((int)($sponsor['is_active'] ?? 0) !== 1) return ['ok' => false, 'error' => 'Sponsor is inactive.'];

        $normalizedNote = trim($note);
        $logicalKey = (string)$clubId . '|' . (string)$sponsorId . '|' . (string)$amount . '|' . mb_strtolower($normalizedNote) . '|' . date('Y-m-d');
        $referenceId = abs(crc32($logicalKey));

        return $this->postEntry(
            $clubId,
            'SPONSOR_INCOME',
            $amount,
            $normalizedNote !== '' ? $normalizedNote : ('Sponsor income: ' . ($sponsor['brand_name'] ?? 'sponsor')),
            null,
            'SPONSOR_INCOME_DAILY',
            $referenceId,
            ['tier' => $sponsor['tier'] ?? 'minor', 'sponsor_id' => $sponsorId, 'logical_key' => $logicalKey]
        );
    }

    public function postSeasonReward(int $clubId, int $seasonId, int $amount, string $reason, string $rewardKey = 'GENERAL'): array {
        if ($amount <= 0) return ['ok' => false, 'error' => 'Reward amount must be positive.'];
        $normalizedKey = strtoupper(trim($rewardKey)) ?: 'GENERAL';
        $referenceId = abs(crc32((string)$seasonId . '|' . (string)$clubId . '|' . $normalizedKey));
        return $this->postEntry($clubId, 'SEASON_REWARD', $amount, $reason, $seasonId, 'SEASON_REWARD', $referenceId, ['reward_key' => $normalizedKey]);
    }

    public function getLedgerByClub(int $clubId, int $limit = 200): array {
        return $this->db->fetchAll(
            "SELECT * FROM club_finance_ledger WHERE club_id = ? ORDER BY id DESC LIMIT ?",
            [$clubId, $limit]
        );
    }

    public function getRecurringEconomySnapshot(int $clubId, int $days = 30): array {
        $rows = $this->db->fetchAll(
            "SELECT entry_type, SUM(amount) AS total_amount, COUNT(*) AS cnt
             FROM club_finance_ledger
             WHERE club_id = ?
               AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
               AND entry_type IN ('COACH_SALARY','PLAYER_WAGE','SPONSOR_INCOME','OPERATING_COST','FACILITY_MAINTENANCE')
             GROUP BY entry_type",
            [$clubId, max(1, $days)]
        );

        $summary = [
            'window_days' => max(1, $days),
            'coach_salary' => 0,
            'player_wage' => 0,
            'sponsor_income' => 0,
            'operating_cost' => 0,
            'facility_maintenance' => 0,
            'entries_count' => 0,
        ];
        foreach ($rows as $row) {
            $type = (string)$row['entry_type'];
            $amount = (int)($row['total_amount'] ?? 0);
            $summary['entries_count'] += (int)($row['cnt'] ?? 0);
            if ($type === 'COACH_SALARY') $summary['coach_salary'] = $amount;
            if ($type === 'PLAYER_WAGE') $summary['player_wage'] = $amount;
            if ($type === 'SPONSOR_INCOME') $summary['sponsor_income'] = $amount;
            if ($type === 'OPERATING_COST') $summary['operating_cost'] = $amount;
            if ($type === 'FACILITY_MAINTENANCE') $summary['facility_maintenance'] = $amount;
        }
        return $summary;
    }

    private function ensureFinanceTables(): void {
        $this->db->execute(
            "CREATE TABLE IF NOT EXISTS club_sponsors (
                id INT AUTO_INCREMENT PRIMARY KEY,
                club_id INT NOT NULL,
                tier ENUM('main','secondary','minor') DEFAULT 'minor',
                brand_name VARCHAR(255) NOT NULL,
                description TEXT,
                contact_link VARCHAR(500),
                banner_url VARCHAR(500),
                is_active BOOLEAN DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (club_id) REFERENCES clubs(id) ON DELETE CASCADE,
                INDEX idx_club_sponsor_tier (club_id, tier)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $this->db->execute(
            "CREATE TABLE IF NOT EXISTS club_owner_funding_events (
                id INT AUTO_INCREMENT PRIMARY KEY,
                club_id INT NOT NULL,
                owner_user_id INT NOT NULL,
                amount BIGINT NOT NULL,
                note VARCHAR(500),
                external_reference VARCHAR(255),
                status ENUM('posted','pending','rejected') DEFAULT 'posted',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (club_id) REFERENCES clubs(id) ON DELETE CASCADE,
                FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE CASCADE,
                INDEX idx_owner_funding_club_date (club_id, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $hasMeta = $this->db->fetchOne(
            "SELECT COLUMN_NAME FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'club_finance_ledger' AND COLUMN_NAME = 'meta_json'"
        );
        if (!$hasMeta) {
            $this->db->execute("ALTER TABLE club_finance_ledger ADD COLUMN meta_json JSON NULL AFTER reference_id");
        }

        $this->db->execute(
            "ALTER TABLE club_finance_ledger
             MODIFY COLUMN entry_type ENUM(
                'COACH_SALARY','MATCH_REWARD','SEASON_REWARD','GOVERNANCE_PENALTY','GOVERNANCE_COMPENSATION',
                'TRANSFER_IN','TRANSFER_OUT','OWNER_FUNDING','SPONSOR_INCOME','PLAYER_WAGE','OPERATING_COST','MANUAL_ADMIN_ADJUSTMENT',
                'FACILITY_UPGRADE','FACILITY_DOWNGRADE_REFUND','FACILITY_MAINTENANCE',
                'WAGE','STAFF_WAGE','PENALTY','PRIZE','OTHER','SPONSOR','TICKET'
             ) NOT NULL"
        );

        $hasRecurringAmount = $this->db->fetchOne(
            "SELECT COLUMN_NAME FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'club_sponsors' AND COLUMN_NAME = 'recurring_amount'"
        );
        if (!$hasRecurringAmount) {
            $this->db->execute("ALTER TABLE club_sponsors ADD COLUMN recurring_amount BIGINT DEFAULT 0 AFTER banner_url");
        }

        $hasRecurringCycle = $this->db->fetchOne(
            "SELECT COLUMN_NAME FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'club_sponsors' AND COLUMN_NAME = 'recurring_cycle_days'"
        );
        if (!$hasRecurringCycle) {
            $this->db->execute("ALTER TABLE club_sponsors ADD COLUMN recurring_cycle_days INT DEFAULT 7 AFTER recurring_amount");
        }

        $hasLastPaid = $this->db->fetchOne(
            "SELECT COLUMN_NAME FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'club_sponsors' AND COLUMN_NAME = 'last_paid_at'"
        );
        if (!$hasLastPaid) {
            $this->db->execute("ALTER TABLE club_sponsors ADD COLUMN last_paid_at DATETIME NULL AFTER recurring_cycle_days");
        }
    }

    private function canClubAffordExpense(int $clubId, int $expenseAmount): bool {
        if ($expenseAmount <= 0) return true;
        $club = $this->db->fetchOne("SELECT balance FROM clubs WHERE id = ?", [$clubId]);
        $balance = (int)($club['balance'] ?? 0);
        return $balance >= $expenseAmount;
    }

    private function estimatePlayerWage(int $overall): int {
        return (int)max(1000, round(800 + (($overall * $overall) * 2.5)));
    }

    private function defaultSponsorRecurringAmount(string $tier): int {
        return match (strtolower(trim($tier))) {
            'main' => 130000,
            'secondary' => 70000,
            default => 30000,
        };
    }

    private function calculateOperatingCost(int $reputation, int $playersCount, int $stadiumLevel, int $hqLevel): int {
        $base = 18000;
        $repComponent = max(0, $reputation) * 110;
        $squadComponent = max(0, $playersCount) * 170;
        $stadiumComponent = max(1, $stadiumLevel) * 2400;
        $hqComponent = max(1, $hqLevel) * 2100;
        return (int)round($base + $repComponent + $squadComponent + $stadiumComponent + $hqComponent);
    }
}
