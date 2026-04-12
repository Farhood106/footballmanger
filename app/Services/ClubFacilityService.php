<?php
// app/Services/ClubFacilityService.php

class ClubFacilityService {
    private Database $db;
    private FinanceService $finance;

    private const FACILITY_CONFIG = [
        'stadium' => [
            'label' => 'Stadium',
            'min_level' => 1,
            'max_level' => 5,
            'upgrade_base' => 600000,
            'maintenance_base' => 9000,
            'image_base' => '/assets/facilities/stadium-l',
        ],
        'training_ground' => [
            'label' => 'Training Ground',
            'min_level' => 1,
            'max_level' => 5,
            'upgrade_base' => 450000,
            'maintenance_base' => 7000,
            'image_base' => '/assets/facilities/training-l',
        ],
        'youth_academy' => [
            'label' => 'Youth Academy',
            'min_level' => 1,
            'max_level' => 5,
            'upgrade_base' => 400000,
            'maintenance_base' => 6500,
            'image_base' => '/assets/facilities/youth-l',
        ],
        'headquarters' => [
            'label' => 'Headquarters',
            'min_level' => 1,
            'max_level' => 5,
            'upgrade_base' => 350000,
            'maintenance_base' => 6000,
            'image_base' => '/assets/facilities/hq-l',
        ],
    ];

    public function __construct(?Database $db = null) {
        $this->db = $db ?? Database::getInstance();
        $this->finance = new FinanceService($this->db);
        $this->ensureFacilityTable();
    }

    public function getFacilitiesForClub(int $clubId): array {
        $this->initializeClubFacilities($clubId);
        $rows = $this->db->fetchAll(
            "SELECT * FROM club_facilities WHERE club_id = ? ORDER BY facility_type ASC",
            [$clubId]
        );

        $result = [];
        foreach ($rows as $row) {
            $type = (string)$row['facility_type'];
            $cfg = self::FACILITY_CONFIG[$type] ?? null;
            if (!$cfg) continue;
            $level = (int)$row['level'];
            $result[] = [
                'id' => (int)$row['id'],
                'club_id' => (int)$row['club_id'],
                'facility_type' => $type,
                'label' => $cfg['label'],
                'level' => $level,
                'min_level' => (int)$cfg['min_level'],
                'max_level' => (int)$cfg['max_level'],
                'next_upgrade_cost' => $this->computeUpgradeCost($type, $level),
                'downgrade_refund' => $this->computeDowngradeRefund($type, $level),
                'daily_maintenance_cost' => $this->computeMaintenanceCost($type, $level),
                'image_ref' => !empty($row['image_url']) ? (string)$row['image_url'] : $this->imageRef($type, $level),
            ];
        }

        return $result;
    }

    public function getFacilityMap(int $clubId): array {
        $rows = $this->getFacilitiesForClub($clubId);
        $map = [];
        foreach ($rows as $row) {
            $map[(string)$row['facility_type']] = $row;
        }
        return $map;
    }

    public function upgradeFacility(int $clubId, string $facilityType, int $actorUserId, bool $isAdmin): array {
        $auth = $this->assertManagePermission($clubId, $actorUserId, $isAdmin);
        if (!($auth['ok'] ?? false)) return $auth;

        $row = $this->getFacilityRow($clubId, $facilityType);
        if (!$row) return ['ok' => false, 'error' => 'Facility type not found.'];

        $level = (int)$row['level'];
        $max = (int)$row['max_level'];
        if ($level >= $max) {
            return ['ok' => false, 'error' => 'Facility is already at max level.'];
        }

        $cost = $this->computeUpgradeCost($facilityType, $level);
        $balance = (int)($this->db->fetchOne("SELECT balance FROM clubs WHERE id = ?", [$clubId])['balance'] ?? 0);
        if ($balance < $cost) {
            return ['ok' => false, 'error' => 'Insufficient balance for upgrade.'];
        }

        $nextLevel = $level + 1;
        $result = $this->finance->postEntry(
            $clubId,
            'FACILITY_UPGRADE',
            -1 * $cost,
            strtoupper($facilityType) . ' upgraded to level ' . $nextLevel,
            null,
            'FACILITY_UPGRADE',
            abs(crc32($clubId . '|' . $facilityType . '|L' . $nextLevel)),
            ['facility_type' => $facilityType, 'from_level' => $level, 'to_level' => $nextLevel]
        );
        if (empty($result['ok'])) {
            return $result;
        }

        $this->db->execute(
            "UPDATE club_facilities SET level = ?, updated_at = NOW() WHERE id = ?",
            [$nextLevel, (int)$row['id']]
        );

        return ['ok' => true, 'facility_type' => $facilityType, 'level' => $nextLevel, 'cost' => $cost];
    }

    public function downgradeFacility(int $clubId, string $facilityType, int $actorUserId, bool $isAdmin): array {
        $auth = $this->assertManagePermission($clubId, $actorUserId, $isAdmin);
        if (!($auth['ok'] ?? false)) return $auth;

        $row = $this->getFacilityRow($clubId, $facilityType);
        if (!$row) return ['ok' => false, 'error' => 'Facility type not found.'];

        $level = (int)$row['level'];
        $min = (int)$row['min_level'];
        if ($level <= $min) {
            return ['ok' => false, 'error' => 'Facility is already at minimum level.'];
        }

        $refund = $this->computeDowngradeRefund($facilityType, $level);
        $nextLevel = $level - 1;

        if ($refund > 0) {
            $result = $this->finance->postEntry(
                $clubId,
                'FACILITY_DOWNGRADE_REFUND',
                $refund,
                strtoupper($facilityType) . ' downgraded to level ' . $nextLevel,
                null,
                'FACILITY_DOWNGRADE',
                abs(crc32($clubId . '|' . $facilityType . '|L' . $nextLevel . '|refund')),
                ['facility_type' => $facilityType, 'from_level' => $level, 'to_level' => $nextLevel]
            );
            if (empty($result['ok'])) {
                return $result;
            }
        }

        $this->db->execute(
            "UPDATE club_facilities SET level = ?, updated_at = NOW() WHERE id = ?",
            [$nextLevel, (int)$row['id']]
        );

        return ['ok' => true, 'facility_type' => $facilityType, 'level' => $nextLevel, 'refund' => $refund];
    }

    public function postDailyMaintenance(string $cycleDate): array {
        $rows = $this->db->fetchAll("SELECT * FROM club_facilities ORDER BY club_id ASC, facility_type ASC");
        $posted = 0;
        foreach ($rows as $row) {
            $clubId = (int)$row['club_id'];
            $type = (string)$row['facility_type'];
            $level = (int)$row['level'];
            $amount = $this->computeMaintenanceCost($type, $level);
            if ($amount <= 0) continue;

            $result = $this->finance->postEntry(
                $clubId,
                'FACILITY_MAINTENANCE',
                -1 * $amount,
                strtoupper($type) . ' maintenance for ' . $cycleDate,
                null,
                'FACILITY_MAINTENANCE_DAILY',
                abs(crc32($clubId . '|' . $type . '|' . $cycleDate)),
                ['facility_type' => $type, 'level' => $level, 'cycle_date' => $cycleDate]
            );
            if (!empty($result['ok'])) $posted++;
        }

        return ['ok' => true, 'posted' => $posted];
    }

    public function getReadinessRecoveryBonus(int $clubId): int {
        $map = $this->getFacilityMap($clubId);
        $level = (int)($map['training_ground']['level'] ?? 1);
        return max(0, $level - 1);
    }

    public function getTrainingDevelopmentBonus(int $clubId): float {
        $map = $this->getFacilityMap($clubId);
        $level = (int)($map['training_ground']['level'] ?? 1);
        return ($level - 1) * 0.05;
    }

    public function getYouthPotentialBonus(int $clubId): float {
        $map = $this->getFacilityMap($clubId);
        $level = (int)($map['youth_academy']['level'] ?? 1);
        return ($level - 1) * 0.04;
    }

    public function getClubPrestigeFoundationBonus(int $clubId): int {
        $map = $this->getFacilityMap($clubId);
        $stadium = (int)($map['stadium']['level'] ?? 1);
        $hq = (int)($map['headquarters']['level'] ?? 1);
        return max(0, (int)floor((($stadium - 1) + ($hq - 1)) / 2));
    }

    private function getFacilityRow(int $clubId, string $facilityType): ?array {
        $this->initializeClubFacilities($clubId);
        $row = $this->db->fetchOne(
            "SELECT cf.*, cfg.min_level, cfg.max_level
             FROM club_facilities cf
             JOIN (
                SELECT 'stadium' facility_type, 1 min_level, 5 max_level
                UNION SELECT 'training_ground', 1, 5
                UNION SELECT 'youth_academy', 1, 5
                UNION SELECT 'headquarters', 1, 5
             ) cfg ON cfg.facility_type = cf.facility_type
             WHERE cf.club_id = ? AND cf.facility_type = ?
             LIMIT 1",
            [$clubId, $facilityType]
        );
        return $row ?: null;
    }

    private function initializeClubFacilities(int $clubId): void {
        if ($clubId <= 0) return;
        foreach (array_keys(self::FACILITY_CONFIG) as $type) {
            $exists = $this->db->fetchOne(
                "SELECT id FROM club_facilities WHERE club_id = ? AND facility_type = ?",
                [$clubId, $type]
            );
            if ($exists) continue;

            $this->db->insert('club_facilities', [
                'club_id' => $clubId,
                'facility_type' => $type,
                'level' => 1,
                'image_url' => null,
            ]);
        }
    }

    private function assertManagePermission(int $clubId, int $actorUserId, bool $isAdmin): array {
        if ($clubId <= 0) return ['ok' => false, 'error' => 'Invalid club.'];
        if ($isAdmin) return ['ok' => true];

        $club = $this->db->fetchOne("SELECT owner_user_id FROM clubs WHERE id = ?", [$clubId]);
        if (!$club) return ['ok' => false, 'error' => 'Club not found.'];
        if ((int)($club['owner_user_id'] ?? 0) !== $actorUserId) {
            return ['ok' => false, 'error' => 'Only owner/admin can manage facilities.'];
        }
        return ['ok' => true];
    }

    private function computeUpgradeCost(string $facilityType, int $currentLevel): int {
        $cfg = self::FACILITY_CONFIG[$facilityType] ?? null;
        if (!$cfg) return 0;
        $max = (int)$cfg['max_level'];
        if ($currentLevel >= $max) return 0;
        return (int)round((int)$cfg['upgrade_base'] * (1 + (($currentLevel - 1) * 0.65)));
    }

    private function computeDowngradeRefund(string $facilityType, int $currentLevel): int {
        if ($currentLevel <= 1) return 0;
        $lastUpgradeCost = $this->computeUpgradeCost($facilityType, $currentLevel - 1);
        return (int)round($lastUpgradeCost * 0.40);
    }

    private function computeMaintenanceCost(string $facilityType, int $level): int {
        $cfg = self::FACILITY_CONFIG[$facilityType] ?? null;
        if (!$cfg) return 0;
        return (int)round((int)$cfg['maintenance_base'] * max(1, $level) * 0.6);
    }

    private function imageRef(string $facilityType, int $level): string {
        $cfg = self::FACILITY_CONFIG[$facilityType] ?? null;
        if (!$cfg) return '/assets/facilities/default.png';
        return (string)$cfg['image_base'] . max(1, $level) . '.png';
    }

    private function ensureFacilityTable(): void {
        $this->db->execute(
            "CREATE TABLE IF NOT EXISTS club_facilities (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                club_id INT NOT NULL,
                facility_type ENUM('stadium','training_ground','youth_academy','headquarters') NOT NULL,
                level INT NOT NULL DEFAULT 1,
                image_url VARCHAR(500) NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_club_facility_type (club_id, facility_type),
                INDEX idx_club_facility_level (club_id, facility_type, level),
                FOREIGN KEY (club_id) REFERENCES clubs(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }
}
