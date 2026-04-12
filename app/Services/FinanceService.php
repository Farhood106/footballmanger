<?php
// app/Services/FinanceService.php

class FinanceService {
    private Database $db;

    private const ALLOWED_ENTRY_TYPES = [
        'COACH_SALARY',
        'MATCH_REWARD',
        'SEASON_REWARD',
        'GOVERNANCE_PENALTY',
        'GOVERNANCE_COMPENSATION',
        'TRANSFER_IN',
        'TRANSFER_OUT',
        'OWNER_FUNDING',
        'SPONSOR_INCOME',
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
                'TRANSFER_IN','TRANSFER_OUT','OWNER_FUNDING','SPONSOR_INCOME','MANUAL_ADMIN_ADJUSTMENT',
                'FACILITY_UPGRADE','FACILITY_DOWNGRADE_REFUND','FACILITY_MAINTENANCE',
                'WAGE','STAFF_WAGE','PENALTY','PRIZE','OTHER','SPONSOR','TICKET'
             ) NOT NULL"
        );
    }
}
