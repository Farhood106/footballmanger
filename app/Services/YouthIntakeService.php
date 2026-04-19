<?php
// app/Services/YouthIntakeService.php

class YouthIntakeService {
    private Database $db;
    private ClubFacilityService $facilities;

    private const POSITIONS = ['GK','CB','LB','RB','CDM','CM','CAM','LW','RW','ST'];
    private const FIRST_NAMES = ['Arman','Nima','Sina','Kian','Peyman','Reza','Ali','Omid','Navid','Daniyal','Farid','Mahan'];
    private const LAST_NAMES = ['Hosseini','Karimi','Rahimi','Jafari','Noori','Azizi','Samadi','Mansouri','Ahmadi','Shirazi','Kazemi','Moradi'];
    private const NATIONALITIES = ['Iran','Turkey','Iraq','Uzbekistan','Qatar','UAE','Saudi Arabia'];

    public function __construct(?Database $db = null) {
        $this->db = $db ?? Database::getInstance();
        $this->facilities = new ClubFacilityService($this->db);
        if ($this->db->shouldRunRuntimeDdlFallback()) {
            $this->ensureYouthIntakeStructures();
        }
    }

    public function generateForSeason(array $clubIds, int $intakeSeasonId, string $intakeKey = 'ROLLOVER_APPLY'): array {
        $clubIds = array_values(array_unique(array_filter(array_map('intval', $clubIds), fn($id) => $id > 0)));
        $generated = 0;
        $skipped = 0;
        foreach ($clubIds as $clubId) {
            $result = $this->generateForClub($clubId, $intakeSeasonId, $intakeKey);
            if (!empty($result['generated_count'])) {
                $generated += (int)$result['generated_count'];
            } else {
                $skipped++;
            }
        }
        return ['ok' => true, 'clubs' => count($clubIds), 'generated_players' => $generated, 'skipped' => $skipped];
    }

    public function generateForClub(int $clubId, int $intakeSeasonId, string $intakeKey): array {
        $existing = $this->db->fetchOne(
            "SELECT id, generated_count FROM youth_intake_logs
             WHERE club_id = ? AND intake_season_id = ? AND intake_key = ?",
            [$clubId, $intakeSeasonId, $intakeKey]
        );
        if ($existing) {
            return ['ok' => true, 'duplicate' => true, 'generated_count' => 0];
        }

        $academyLevel = $this->resolveAcademyLevel($clubId);
        $count = min(3, max(1, 1 + (int)floor(($academyLevel - 1) / 2)));
        $createdIds = [];
        for ($i = 1; $i <= $count; $i++) {
            $profile = $this->buildYouthProfile($clubId, $intakeSeasonId, $academyLevel, $i);
            $playerId = (int)$this->db->insert('players', $profile);
            $createdIds[] = $playerId;
        }

        try {
            $this->db->insert('youth_intake_logs', [
                'club_id' => $clubId,
                'intake_season_id' => $intakeSeasonId,
                'intake_key' => $intakeKey,
                'academy_level' => $academyLevel,
                'generated_count' => count($createdIds),
                'generated_player_ids_json' => json_encode($createdIds, JSON_UNESCAPED_UNICODE),
            ]);
        } catch (Throwable $e) {
            $duplicate = $this->db->fetchOne(
                "SELECT id FROM youth_intake_logs
                 WHERE club_id = ? AND intake_season_id = ? AND intake_key = ?",
                [$clubId, $intakeSeasonId, $intakeKey]
            );
            if ($duplicate) {
                return ['ok' => true, 'duplicate' => true, 'generated_count' => 0];
            }
            throw $e;
        }

        return ['ok' => true, 'generated_count' => count($createdIds), 'player_ids' => $createdIds];
    }

    public function getRecentClubIntakeLogs(int $clubId, int $limit = 10): array {
        return $this->db->fetchAll(
            "SELECT yil.*, s.name AS season_name
             FROM youth_intake_logs yil
             LEFT JOIN seasons s ON s.id = yil.intake_season_id
             WHERE yil.club_id = ?
             ORDER BY yil.created_at DESC
             LIMIT ?",
            [$clubId, $limit]
        );
    }

    private function buildYouthProfile(int $clubId, int $seasonId, int $academyLevel, int $slot): array {
        $seed = abs(crc32($clubId . '|' . $seasonId . '|A' . $academyLevel . '|S' . $slot));
        $position = self::POSITIONS[$seed % count(self::POSITIONS)];
        $age = 16 + ($seed % 3);
        $birthDate = date('Y-m-d', strtotime('-' . $age . ' years +' . (($seed % 300) + 10) . ' days'));
        $overall = max(47, min(73, 51 + ($academyLevel * 2) + (($seed % 8) - 3)));
        $potential = max($overall + 4, min(91, $overall + 8 + ($academyLevel * 2) + ($seed % 7)));
        $moraleScore = 70 + ($seed % 9);
        $fitness = 92 + ($seed % 6);
        $marketValue = PlayerCareerService::computeMarketValue($overall, $potential, $age, 0, $fitness, $moraleScore, false, 0);

        $base = $overall + ($academyLevel - 1);
        $attrSeed = fn(int $shift, int $min = -8, int $max = 8): int => max(35, min(88, $base + $min + ((($seed >> $shift) % ($max - $min + 1)))));
        $nameFirst = self::FIRST_NAMES[$seed % count(self::FIRST_NAMES)];
        $nameLast = self::LAST_NAMES[($seed >> 3) % count(self::LAST_NAMES)];
        $nationality = self::NATIONALITIES[($seed >> 5) % count(self::NATIONALITIES)];

        return [
            'club_id' => $clubId,
            'first_name' => $nameFirst,
            'last_name' => $nameLast,
            'nationality' => $nationality,
            'birth_date' => $birthDate,
            'position' => $position,
            'preferred_foot' => (($seed % 10) === 0) ? 'BOTH' : ((($seed % 2) === 0) ? 'RIGHT' : 'LEFT'),
            'pace' => $attrSeed(1),
            'shooting' => $attrSeed(2),
            'passing' => $attrSeed(3),
            'dribbling' => $attrSeed(4),
            'defending' => $attrSeed(5),
            'physical' => $attrSeed(6),
            'overall' => $overall,
            'potential' => $potential,
            'form' => 6.4,
            'fatigue' => max(0, 100 - $fitness),
            'morale' => round($moraleScore / 10, 1),
            'fitness' => $fitness,
            'morale_score' => $moraleScore,
            'squad_role' => 'PROSPECT',
            'last_played_at' => null,
            'last_minutes_played' => 0,
            'wage' => max(500, (int)round($overall * 28)),
            'contract_end' => date('Y-m-d', strtotime('+3 years')),
            'market_value' => $marketValue,
            'is_transfer_listed' => 0,
            'asking_price' => null,
            'transfer_listed_at' => null,
            'is_injured' => 0,
            'injury_days' => 0,
            'is_on_loan' => 0,
            'is_retired' => 0,
            'is_academy_origin' => 1,
            'academy_origin_club_id' => $clubId,
            'academy_intake_season_id' => $seasonId,
            'academy_intake_batch_key' => 'S' . $seasonId . '-C' . $clubId . '-K' . $slot,
            'growth_rate' => 1.05,
        ];
    }

    private function resolveAcademyLevel(int $clubId): int {
        $map = $this->facilities->getFacilityMap($clubId);
        return max(1, min(5, (int)($map['youth_academy']['level'] ?? 1)));
    }

    private function ensureYouthIntakeStructures(): void {
        $this->ensureAcademyOriginColumns();
        $this->db->execute(
            "CREATE TABLE IF NOT EXISTS youth_intake_logs (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                club_id INT NOT NULL,
                intake_season_id INT NOT NULL,
                intake_key VARCHAR(64) NOT NULL,
                academy_level INT NOT NULL DEFAULT 1,
                generated_count INT NOT NULL DEFAULT 0,
                generated_player_ids_json JSON NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_club_season_intake (club_id, intake_season_id, intake_key),
                INDEX idx_intake_club_created (club_id, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    private function ensureAcademyOriginColumns(): void {
        $columns = $this->db->fetchAll(
            "SELECT COLUMN_NAME AS column_name
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'players'"
        );
        $existing = [];
        foreach ($columns as $row) {
            $existing[(string)($row['column_name'] ?? '')] = true;
        }

        if (empty($existing['is_academy_origin'])) {
            $this->db->execute("ALTER TABLE players ADD COLUMN is_academy_origin BOOLEAN DEFAULT 0 AFTER is_retired");
        }
        if (empty($existing['academy_origin_club_id'])) {
            $this->db->execute("ALTER TABLE players ADD COLUMN academy_origin_club_id INT NULL AFTER is_academy_origin");
        }
        if (empty($existing['academy_intake_season_id'])) {
            $this->db->execute("ALTER TABLE players ADD COLUMN academy_intake_season_id INT NULL AFTER academy_origin_club_id");
        }
        if (empty($existing['academy_intake_batch_key'])) {
            $this->db->execute("ALTER TABLE players ADD COLUMN academy_intake_batch_key VARCHAR(64) NULL AFTER academy_intake_season_id");
        }
    }
}
