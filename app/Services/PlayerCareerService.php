<?php
// app/Services/PlayerCareerService.php

class PlayerCareerService {
    private Database $db;
    private ClubFacilityService $facilities;

    public function __construct(?Database $db = null) {
        $this->db = $db ?? Database::getInstance();
        $this->facilities = new ClubFacilityService($this->db);
        $this->ensureReadinessColumns();
        $this->ensureCareerHistoryTable();
    }

    public static function computeDevelopmentSignal(
        int $age,
        int $overall,
        int $potential,
        int $recentMatches,
        int $fitness,
        int $moraleScore,
        bool $isInjured
    ): float {
        $ageFactor = $age <= 22 ? 0.7 : ($age <= 28 ? 0.2 : -0.45);
        $headroom = max(0, $potential - $overall) / 25.0;
        $activity = min(1.0, $recentMatches / 8.0) * 0.35;
        $readiness = (((max(0, min(100, $fitness)) - 50) / 50) * 0.2) + (((max(0, min(100, $moraleScore)) - 50) / 50) * 0.2);
        $injuryPenalty = $isInjured ? -0.35 : 0.0;
        return $ageFactor + $headroom + $activity + $readiness + $injuryPenalty;
    }

    public static function computeMarketValue(
        int $overall,
        int $potential,
        int $age,
        int $recentMatches,
        int $fitness,
        int $moraleScore,
        bool $isInjured,
        int $careerAppearances
    ): int {
        $base = max(100000, ($overall ** 2) * 5500);
        $potentialBoost = max(0, $potential - $overall) * 18000;
        $ageMod = $age <= 22 ? 1.35 : ($age <= 27 ? 1.15 : ($age <= 31 ? 1.0 : 0.78));
        $activityMod = 0.85 + min(0.25, $recentMatches * 0.02);
        $fitnessMod = 0.92 + (max(0, min(100, $fitness)) / 100) * 0.16;
        $moraleMod = 0.94 + (max(0, min(100, $moraleScore)) / 100) * 0.12;
        $injuryMod = $isInjured ? 0.86 : 1.0;
        $experienceMod = 0.9 + min(0.18, max(0, $careerAppearances) / 250);

        $value = ($base + $potentialBoost) * $ageMod * $activityMod * $fitnessMod * $moraleMod * $injuryMod * $experienceMod;
        return (int)max(75000, min(300000000, round($value)));
    }

    public function applyDailyRecoveryAndDrift(): array {
        $players = $this->db->fetchAll(
            "SELECT id, club_id, fitness, morale_score, morale, is_injured
             FROM players
             WHERE is_retired = 0
             ORDER BY id ASC"
        );

        foreach ($players as $player) {
            $clubId = (int)($player['club_id'] ?? 0);
            $recoveryBonus = $clubId > 0 ? $this->facilities->getReadinessRecoveryBonus($clubId) : 0;
            $baseRecovery = !empty($player['is_injured']) ? 2 : 6;
            $newFitness = min(100, max(0, (int)($player['fitness'] ?? 100) + $baseRecovery + $recoveryBonus));

            $moraleScore = (int)($player['morale_score'] ?? 70);
            $moraleDrift = $moraleScore < 55 ? 2 : ($moraleScore > 75 ? -1 : 0);
            $newMoraleScore = min(100, max(0, $moraleScore + $moraleDrift));

            $this->db->execute(
                "UPDATE players
                 SET fitness = ?, morale_score = ?, fatigue = ?, morale = ?
                 WHERE id = ?",
                [
                    $newFitness,
                    $newMoraleScore,
                    max(0, min(100, 100 - $newFitness)),
                    round($newMoraleScore / 10, 1),
                    (int)$player['id']
                ]
            );
        }

        return ['ok' => true, 'updated' => count($players)];
    }

    public function runDailyDevelopmentAndValuation(string $cycleDate): array {
        $players = $this->db->fetchAll(
            "SELECT p.*
             FROM players p
             WHERE p.is_retired = 0
             ORDER BY p.id ASC"
        );
        $adjusted = 0;
        foreach ($players as $player) {
            $playerId = (int)$player['id'];
            $age = $this->ageYears((string)$player['birth_date']);
            $recentMatches = (int)($this->db->fetchOne(
                "SELECT COUNT(*) AS c
                 FROM player_match_ratings pmr
                 JOIN matches m ON m.id = pmr.match_id
                 WHERE pmr.player_id = ? AND m.played_at IS NOT NULL
                   AND DATE(m.played_at) >= DATE_SUB(?, INTERVAL 30 DAY)",
                [$playerId, $cycleDate]
            )['c'] ?? 0);

            $signal = self::computeDevelopmentSignal(
                $age,
                (int)$player['overall'],
                (int)$player['potential'],
                $recentMatches,
                (int)($player['fitness'] ?? 100),
                (int)($player['morale_score'] ?? 70),
                !empty($player['is_injured'])
            );
            $clubId = (int)($player['club_id'] ?? 0);
            if ($clubId > 0) {
                $signal += $this->facilities->getTrainingDevelopmentBonus($clubId);
                if ($age <= 21) {
                    $signal += $this->facilities->getYouthPotentialBonus($clubId);
                }
            }

            $overall = (int)$player['overall'];
            if ($signal >= 0.85 && $overall < (int)$player['potential']) {
                $overall++;
                $adjusted++;
            } elseif ($signal <= -0.55 && $overall > 40) {
                $overall--;
                $adjusted++;
            }

            $careerApps = (int)($this->db->fetchOne("SELECT SUM(appearances) AS c FROM player_season_stats WHERE player_id = ?", [$playerId])['c'] ?? 0);
            $marketValue = self::computeMarketValue(
                $overall,
                (int)$player['potential'],
                $age,
                $recentMatches,
                (int)($player['fitness'] ?? 100),
                (int)($player['morale_score'] ?? 70),
                !empty($player['is_injured']),
                $careerApps
            );

            $this->db->execute(
                "UPDATE players SET overall = ?, market_value = ? WHERE id = ?",
                [$overall, $marketValue, $playerId]
            );
        }

        return ['ok' => true, 'adjusted' => $adjusted];
    }

    public function applyPostMatchPlayerUpdate(
        array $player,
        ?array $rating,
        bool $started,
        int $minutesPlayed,
        bool $wasInjuredEvent
    ): void {
        $playerId = (int)$player['id'];
        $fitness = (int)($player['fitness'] ?? max(0, 100 - (int)($player['fatigue'] ?? 0)));
        $moraleScore = (int)($player['morale_score'] ?? (int)round(((float)($player['morale'] ?? 7.0)) * 10));

        $fitnessDrop = $started ? min(28, 10 + (int)floor($minutesPlayed / 8)) : max(0, (int)floor($minutesPlayed / 12));
        $newFitness = max(0, min(100, $fitness - $fitnessDrop));

        $result = (string)($rating['result'] ?? 'D');
        $resultDelta = $result === 'W' ? 6 : ($result === 'L' ? -6 : 1);
        $ratingScore = (int)round(((float)($rating['rating'] ?? 6.5) - 6.5) * 6);
        $playTimeDelta = $started ? 2 : ($minutesPlayed === 0 ? -2 : 0);
        $injuryDelta = $wasInjuredEvent ? -8 : 0;
        $newMoraleScore = max(0, min(100, $moraleScore + $resultDelta + $ratingScore + $playTimeDelta + $injuryDelta));

        $newForm = min(10.0, max(1.0, (((float)($player['form'] ?? 6.5) * 4) + (float)($rating['rating'] ?? 6.5)) / 5));

        $this->db->execute(
            "UPDATE players
             SET form = ?, fitness = ?, morale_score = ?, fatigue = ?, morale = ?
             WHERE id = ?",
            [
                round($newForm, 1),
                $newFitness,
                $newMoraleScore,
                max(0, min(100, 100 - $newFitness)),
                round($newMoraleScore / 10, 1),
                $playerId
            ]
        );
    }

    public function upsertCareerHistoryFromSeasonStats(int $playerId, int $seasonId): void {
        $rows = $this->db->fetchAll(
            "SELECT *
             FROM player_season_stats
             WHERE player_id = ? AND season_id = ?",
            [$playerId, $seasonId]
        );
        foreach ($rows as $row) {
            $existing = $this->db->fetchOne(
                "SELECT id FROM player_career_history WHERE player_id = ? AND season_id = ? AND club_id = ?",
                [$playerId, (int)$row['season_id'], (int)$row['club_id']]
            );
            if ($existing) {
                $this->db->execute(
                    "UPDATE player_career_history
                     SET appearances = ?, starts = ?, minutes_played = ?, goals = ?, assists = ?,
                         yellow_cards = ?, red_cards = ?, avg_rating = ?, updated_at = NOW()
                     WHERE id = ?",
                    [
                        (int)$row['appearances'],
                        (int)($row['starts'] ?? 0),
                        (int)($row['minutes_played'] ?? 0),
                        (int)$row['goals'],
                        (int)$row['assists'],
                        (int)$row['yellow_cards'],
                        (int)$row['red_cards'],
                        (float)$row['avg_rating'],
                        (int)$existing['id']
                    ]
                );
                continue;
            }

            $this->db->insert('player_career_history', [
                'player_id' => $playerId,
                'season_id' => (int)$row['season_id'],
                'club_id' => (int)$row['club_id'],
                'appearances' => (int)$row['appearances'],
                'starts' => (int)($row['starts'] ?? 0),
                'minutes_played' => (int)($row['minutes_played'] ?? 0),
                'goals' => (int)$row['goals'],
                'assists' => (int)$row['assists'],
                'yellow_cards' => (int)$row['yellow_cards'],
                'red_cards' => (int)$row['red_cards'],
                'avg_rating' => (float)$row['avg_rating'],
            ]);
        }
    }

    private function ageYears(string $birthDate): int {
        try {
            $birth = new DateTimeImmutable($birthDate);
            return max(15, (int)$birth->diff(new DateTimeImmutable('today'))->y);
        } catch (Throwable $e) {
            return 25;
        }
    }

    private function ensureCareerHistoryTable(): void {
        $this->db->execute(
            "CREATE TABLE IF NOT EXISTS player_career_history (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                player_id INT NOT NULL,
                season_id INT NOT NULL,
                club_id INT NOT NULL,
                appearances INT DEFAULT 0,
                starts INT DEFAULT 0,
                minutes_played INT DEFAULT 0,
                goals INT DEFAULT 0,
                assists INT DEFAULT 0,
                yellow_cards INT DEFAULT 0,
                red_cards INT DEFAULT 0,
                avg_rating DECIMAL(3,1) DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_player_season_club_history (player_id, season_id, club_id),
                FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
                FOREIGN KEY (season_id) REFERENCES seasons(id) ON DELETE CASCADE,
                FOREIGN KEY (club_id) REFERENCES clubs(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    private function ensureReadinessColumns(): void {
        $hasFitness = $this->db->fetchOne(
            "SELECT COLUMN_NAME FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'players' AND COLUMN_NAME = 'fitness'"
        );
        if (!$hasFitness) {
            $this->db->execute("ALTER TABLE players ADD COLUMN fitness INT DEFAULT 100 AFTER morale");
        }

        $hasMoraleScore = $this->db->fetchOne(
            "SELECT COLUMN_NAME FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'players' AND COLUMN_NAME = 'morale_score'"
        );
        if (!$hasMoraleScore) {
            $this->db->execute("ALTER TABLE players ADD COLUMN morale_score INT DEFAULT 70 AFTER fitness");
        }

        $hasStarts = $this->db->fetchOne(
            "SELECT COLUMN_NAME FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'player_season_stats' AND COLUMN_NAME = 'starts'"
        );
        if (!$hasStarts) {
            $this->db->execute("ALTER TABLE player_season_stats ADD COLUMN starts INT DEFAULT 0 AFTER appearances");
        }
    }
}
