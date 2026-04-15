<?php
// app/Services/YouthAcademyService.php

class YouthAcademyService {
    private Database $db;
    private ClubFacilityService $facilities;

    private array $firstNames = ['Ali','Reza','Amir','Mehdi','Saeid','Arman','Nima','Pouya','Milad','Aria','Kian','Navid'];
    private array $lastNames = ['Ahmadi','Mohammadi','Karimi','Rahimi','Hosseini','Ghaemi','Jafari','Moradi','Noori','Sadeghi'];
    private array $positions = ['GK','LB','RB','CB','CDM','CM','CAM','LW','RW','ST','CF'];

    public function __construct(?Database $db = null) {
        $this->db = $db ?? Database::getInstance();
        $this->facilities = new ClubFacilityService($this->db);
        $this->ensureYouthSchema();
    }

    public function generateAnnualIntakeForSeason(int $seasonId): array {
        if ($seasonId <= 0) {
            return ['ok' => false, 'error' => 'Invalid season id for youth intake.'];
        }

        $clubRows = $this->db->fetchAll(
            "SELECT DISTINCT cs.club_id
             FROM club_seasons cs
             WHERE cs.season_id = ?
             ORDER BY cs.club_id ASC",
            [$seasonId]
        );

        $summary = [
            'ok' => true,
            'season_id' => $seasonId,
            'clubs_considered' => count($clubRows),
            'clubs_generated' => 0,
            'clubs_skipped' => 0,
            'players_created' => 0,
            'intakes' => [],
        ];

        foreach ($clubRows as $row) {
            $clubId = (int)$row['club_id'];
            $intake = $this->generateClubIntakeForSeason($seasonId, $clubId);
            if (!empty($intake['created'])) {
                $summary['clubs_generated']++;
                $summary['players_created'] += (int)($intake['players_created'] ?? 0);
            } else {
                $summary['clubs_skipped']++;
            }
            $summary['intakes'][] = $intake;
        }

        return $summary;
    }

    public function generateClubIntakeForSeason(int $seasonId, int $clubId): array {
        if ($seasonId <= 0 || $clubId <= 0) {
            return ['ok' => false, 'created' => false, 'error' => 'Invalid season/club for intake.'];
        }

        $existing = $this->db->fetchOne(
            "SELECT id, intake_count FROM youth_intakes WHERE season_id = ? AND club_id = ? LIMIT 1",
            [$seasonId, $clubId]
        );
        if ($existing) {
            return [
                'ok' => true,
                'created' => false,
                'duplicate_prevented' => true,
                'club_id' => $clubId,
                'season_id' => $seasonId,
                'players_created' => (int)($existing['intake_count'] ?? 0),
            ];
        }

        $academyLevel = $this->getYouthAcademyLevel($clubId);
        $intakeSize = $this->determineIntakeSize($seasonId, $clubId, $academyLevel);
        $createdPlayers = [];

        for ($slot = 1; $slot <= $intakeSize; $slot++) {
            $payload = $this->buildGeneratedPlayerPayload($seasonId, $clubId, $academyLevel, $slot);
            $playerId = $this->db->insert('players', $payload);
            $createdPlayers[] = [
                'player_id' => $playerId,
                'name' => trim($payload['first_name'] . ' ' . $payload['last_name']),
                'position' => $payload['position'],
                'overall' => (int)$payload['overall'],
                'potential' => (int)$payload['potential'],
                'market_value' => (int)$payload['market_value'],
            ];
        }

        $this->db->insert('youth_intakes', [
            'season_id' => $seasonId,
            'club_id' => $clubId,
            'academy_level' => $academyLevel,
            'intake_count' => count($createdPlayers),
            'intake_json' => json_encode($createdPlayers, JSON_UNESCAPED_UNICODE),
            'generated_at' => date('Y-m-d H:i:s'),
        ]);

        return [
            'ok' => true,
            'created' => true,
            'duplicate_prevented' => false,
            'club_id' => $clubId,
            'season_id' => $seasonId,
            'academy_level' => $academyLevel,
            'players_created' => count($createdPlayers),
            'players' => $createdPlayers,
        ];
    }

    public function getLatestIntakesForClub(int $clubId, int $limit = 3): array {
        return $this->db->fetchAll(
            "SELECT yi.*, s.name AS season_name
             FROM youth_intakes yi
             LEFT JOIN seasons s ON s.id = yi.season_id
             WHERE yi.club_id = ?
             ORDER BY yi.season_id DESC, yi.id DESC
             LIMIT " . max(1, (int)$limit),
            [$clubId]
        );
    }

    public function getAcademyPlayersForClub(int $clubId, int $limit = 25): array {
        return $this->db->fetchAll(
            "SELECT p.id, p.first_name, p.last_name, p.position, p.overall, p.potential, p.market_value,
                    p.birth_date, p.youth_intake_season_id, p.academy_origin_club_id
             FROM players p
             WHERE p.club_id = ?
               AND p.academy_origin_club_id = ?
               AND p.is_retired = 0
             ORDER BY p.youth_intake_season_id DESC, p.overall DESC, p.id DESC
             LIMIT " . max(1, (int)$limit),
            [$clubId, $clubId]
        );
    }

    private function buildGeneratedPlayerPayload(int $seasonId, int $clubId, int $academyLevel, int $slot): array {
        $seed = $seasonId . '|' . $clubId . '|slot|' . $slot;
        $position = $this->positions[$this->roll($seed . '|position', 0, count($this->positions) - 1)];

        $age = $this->roll($seed . '|age', 16, 18);
        $overallBase = 48 + ($academyLevel * 2);
        $overall = $this->clamp($overallBase + $this->roll($seed . '|ovr_delta', -4, 5), 42, 75);

        $potentialHeadroom = 8 + ($academyLevel * 2) + $this->roll($seed . '|pot_headroom', 0, 8);
        $potential = $this->clamp(max($overall + 4, $overall + $potentialHeadroom), 58, 94);

        $attributes = $this->buildAttributesForPosition($position, $overall, $seed);

        $fitness = $this->clamp(80 + $this->roll($seed . '|fitness', 0, 16), 70, 100);
        $moraleScore = $this->clamp(60 + ($academyLevel * 3) + $this->roll($seed . '|morale', 0, 16), 45, 95);
        $birthDate = (new DateTimeImmutable('today'))
            ->modify('-' . $age . ' years')
            ->modify('-' . $this->roll($seed . '|day_offset', 0, 364) . ' days')
            ->format('Y-m-d');

        $marketValue = PlayerCareerService::computeMarketValue(
            $overall,
            $potential,
            $age,
            0,
            $fitness,
            $moraleScore,
            false,
            0
        );

        return [
            'club_id' => $clubId,
            'first_name' => $this->firstNames[$this->roll($seed . '|first_name', 0, count($this->firstNames) - 1)],
            'last_name' => $this->lastNames[$this->roll($seed . '|last_name', 0, count($this->lastNames) - 1)],
            'nationality' => 'Iran',
            'birth_date' => $birthDate,
            'position' => $position,
            'preferred_foot' => $this->roll($seed . '|foot', 0, 100) <= 18 ? 'LEFT' : 'RIGHT',
            'pace' => $attributes['pace'],
            'shooting' => $attributes['shooting'],
            'passing' => $attributes['passing'],
            'dribbling' => $attributes['dribbling'],
            'defending' => $attributes['defending'],
            'physical' => $attributes['physical'],
            'overall' => $overall,
            'potential' => $potential,
            'form' => 6.3,
            'fatigue' => max(0, 100 - $fitness),
            'morale' => round($moraleScore / 10, 1),
            'fitness' => $fitness,
            'morale_score' => $moraleScore,
            'wage' => (int)max(1200, round($marketValue * 0.0009)),
            'contract_end' => (new DateTimeImmutable('today'))->modify('+3 years')->format('Y-m-d'),
            'market_value' => $marketValue,
            'academy_origin_club_id' => $clubId,
            'youth_intake_season_id' => $seasonId,
            'is_academy_product' => 1,
        ];
    }

    private function buildAttributesForPosition(string $position, int $overall, string $seed): array {
        $attrs = [
            'pace' => $overall,
            'shooting' => $overall,
            'passing' => $overall,
            'dribbling' => $overall,
            'defending' => $overall,
            'physical' => $overall,
        ];

        $boostMap = [
            'GK' => ['defending' => 5, 'passing' => 2, 'shooting' => -8, 'dribbling' => -5, 'pace' => -6],
            'CB' => ['defending' => 7, 'physical' => 5, 'shooting' => -6, 'dribbling' => -5],
            'LB' => ['pace' => 5, 'defending' => 4, 'passing' => 3, 'shooting' => -3],
            'RB' => ['pace' => 5, 'defending' => 4, 'passing' => 3, 'shooting' => -3],
            'CDM' => ['defending' => 6, 'passing' => 4, 'physical' => 3, 'shooting' => -2],
            'CM' => ['passing' => 6, 'dribbling' => 3, 'defending' => 2],
            'CAM' => ['passing' => 7, 'dribbling' => 6, 'shooting' => 3, 'defending' => -4],
            'LW' => ['pace' => 7, 'dribbling' => 6, 'shooting' => 4, 'defending' => -5],
            'RW' => ['pace' => 7, 'dribbling' => 6, 'shooting' => 4, 'defending' => -5],
            'ST' => ['shooting' => 8, 'physical' => 4, 'dribbling' => 3, 'defending' => -6],
            'CF' => ['shooting' => 6, 'passing' => 5, 'dribbling' => 5, 'defending' => -5],
        ];

        foreach (($boostMap[$position] ?? []) as $key => $delta) {
            $attrs[$key] += $delta;
        }

        foreach ($attrs as $key => $value) {
            $attrs[$key] = $this->clamp($value + $this->roll($seed . '|attr|' . $key, -3, 3), 35, 88);
        }

        return $attrs;
    }

    private function determineIntakeSize(int $seasonId, int $clubId, int $academyLevel): int {
        $base = 1;
        $secondChance = 15 + ($academyLevel * 12);
        $thirdChance = max(0, ($academyLevel - 2) * 10);

        $count = $base;
        if ($this->roll($seasonId . '|' . $clubId . '|second_slot', 1, 100) <= $secondChance) {
            $count++;
        }
        if ($count >= 2 && $this->roll($seasonId . '|' . $clubId . '|third_slot', 1, 100) <= $thirdChance) {
            $count++;
        }
        return $this->clamp($count, 1, 3);
    }

    private function getYouthAcademyLevel(int $clubId): int {
        $map = $this->facilities->getFacilityMap($clubId);
        return $this->clamp((int)($map['youth_academy']['level'] ?? 1), 1, 5);
    }

    private function roll(string $seed, int $min, int $max): int {
        $max = max($min, $max);
        $hash = (float)sprintf('%u', crc32($seed));
        $fraction = $hash / 4294967295;
        return (int)floor($min + ($fraction * (($max - $min) + 1)));
    }

    private function clamp(int $value, int $min, int $max): int {
        return max($min, min($max, $value));
    }

    private function ensureYouthSchema(): void {
        $this->db->execute(
            "CREATE TABLE IF NOT EXISTS youth_intakes (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                season_id INT NOT NULL,
                club_id INT NOT NULL,
                academy_level INT NOT NULL DEFAULT 1,
                intake_count INT NOT NULL DEFAULT 0,
                intake_json JSON NULL,
                generated_at DATETIME NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_youth_intake_season_club (season_id, club_id),
                INDEX idx_youth_intake_club_season (club_id, season_id),
                FOREIGN KEY (season_id) REFERENCES seasons(id) ON DELETE CASCADE,
                FOREIGN KEY (club_id) REFERENCES clubs(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $hasOriginClub = $this->db->fetchOne(
            "SELECT COLUMN_NAME FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'players' AND COLUMN_NAME = 'academy_origin_club_id'"
        );
        if (!$hasOriginClub) {
            $this->db->execute("ALTER TABLE players ADD COLUMN academy_origin_club_id INT NULL AFTER market_value");
            $this->db->execute("ALTER TABLE players ADD INDEX idx_players_academy_origin (academy_origin_club_id)");
            $this->db->execute("ALTER TABLE players ADD CONSTRAINT fk_players_academy_origin_club FOREIGN KEY (academy_origin_club_id) REFERENCES clubs(id) ON DELETE SET NULL");
        }

        $hasIntakeSeason = $this->db->fetchOne(
            "SELECT COLUMN_NAME FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'players' AND COLUMN_NAME = 'youth_intake_season_id'"
        );
        if (!$hasIntakeSeason) {
            $this->db->execute("ALTER TABLE players ADD COLUMN youth_intake_season_id INT NULL AFTER academy_origin_club_id");
            $this->db->execute("ALTER TABLE players ADD INDEX idx_players_youth_intake_season (youth_intake_season_id)");
            $this->db->execute("ALTER TABLE players ADD CONSTRAINT fk_players_youth_intake_season FOREIGN KEY (youth_intake_season_id) REFERENCES seasons(id) ON DELETE SET NULL");
        }

        $hasAcademyFlag = $this->db->fetchOne(
            "SELECT COLUMN_NAME FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'players' AND COLUMN_NAME = 'is_academy_product'"
        );
        if (!$hasAcademyFlag) {
            $this->db->execute("ALTER TABLE players ADD COLUMN is_academy_product BOOLEAN DEFAULT 0 AFTER youth_intake_season_id");
            $this->db->execute("ALTER TABLE players ADD INDEX idx_players_academy_product (club_id, is_academy_product)");
        }
    }
}
