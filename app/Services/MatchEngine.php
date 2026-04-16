<?php
// app/Services/MatchEngine.php

class MatchEngine {
    private Database $db;
    private PlayerCareerService $playerCareer;
    private WorldHistoryService $history;

    // تأثیر تاکتیک‌ها روی هم (rock-paper-scissors style)
    private const TACTIC_MATRIX = [
        'PRESSING'   => ['POSSESSION' => 1.15, 'COUNTER' => 0.85, 'ATTACKING' => 1.05],
        'COUNTER'    => ['PRESSING' => 1.15, 'ATTACKING' => 1.10, 'BALANCED' => 1.05],
        'POSSESSION' => ['COUNTER' => 1.15, 'DEFENSIVE' => 1.10, 'BALANCED' => 1.05],
        'ATTACKING'  => ['DEFENSIVE' => 1.20, 'BALANCED' => 1.10, 'PRESSING' => 0.95],
        'DEFENSIVE'  => ['ATTACKING' => 0.85, 'PRESSING' => 1.05, 'COUNTER' => 0.90],
        'BALANCED'   => [],
    ];

    private const HOME_ADVANTAGE = 1.08;
    private const INJURY_CHANCE = 0.015;
    private const YELLOW_CARD_CHANCE = 0.12;
    private const RED_CARD_CHANCE = 0.015;

    public function __construct() {
        $this->db = Database::getInstance();
        $this->playerCareer = new PlayerCareerService($this->db);
        $this->history = new WorldHistoryService($this->db);
    }

    public function simulate(int $matchId): array {
        $match = $this->claimScheduledMatch($matchId);

        try {
            // ۱. بارگذاری داده‌ها
            $homeSquad = $this->getSquad((int)$match['home_club_id'], (int)$match['id']);
        $awaySquad = $this->getSquad((int)$match['away_club_id'], (int)$match['id']);
        $homeTactics = $this->getTactics($match['home_club_id']);
        $awayTactics = $this->getTactics($match['away_club_id']);

        // ۲. محاسبه قدرت تیم‌ها
        $homeStrength = $this->calculateTeamStrength($homeSquad, $homeTactics);
        $awayStrength = $this->calculateTeamStrength($awaySquad, $awayTactics);

        // ۳. محاسبه xG
        $xG = $this->calculateXG($homeStrength, $awayStrength, $homeTactics, $awayTactics);

        // ۴. تولید نتیجه با توزیع پوآسون
        $homeGoals = $this->poissonRandom($xG['home']);
        $awayGoals = $this->poissonRandom($xG['away']);

        // ۵. تولید رویدادها
        $events = $this->generateEvents($matchId, $match, $homeSquad, $awaySquad, $homeGoals, $awayGoals);

        // ۶. تولید آمار بازی
        $stats = $this->generateStats($homeStrength, $awayStrength, $homeGoals, $awayGoals);

        // ۷. محاسبه ریتینگ بازیکنان
        $ratings = $this->calculateRatings($homeSquad, $awaySquad, $events, $homeGoals, $awayGoals);

        // ۸. ذخیره نتیجه
        $this->saveResult($matchId, $homeGoals, $awayGoals, $stats, $xG, $events, $ratings);

        // ۹. آپدیت وضعیت بازیکنان
        $this->updatePlayerStates($match, $homeSquad, $awaySquad, $events, $ratings);

        // ۱۰. آپدیت جدول
        $this->updateStandings($match, $homeGoals, $awayGoals);

        // ۱۱. match/week awards
        $this->recordMatchAwards($match, $homeSquad, $awaySquad, $ratings);

            return [
                'match_id' => $matchId,
                'home_score' => $homeGoals,
                'away_score' => $awayGoals,
                'home_xg' => round($xG['home'], 2),
                'away_xg' => round($xG['away'], 2),
                'events' => $events,
                'stats' => $stats,
                'ratings' => $ratings
            ];
        } catch (Throwable $e) {
            // rollback claim so the match can be retried safely
            $this->db->query("UPDATE matches SET status = 'SCHEDULED' WHERE id = ? AND status = 'LIVE'", [$matchId]);
            throw $e;
        }
    }

    private function recordMatchAwards(array $match, array $homeSquad, array $awaySquad, array $ratings): void {
        if (empty($ratings)) return;
        usort($ratings, fn($a, $b) => ((float)$b['rating'] <=> (float)$a['rating']) ?: ((int)$a['player_id'] <=> (int)$b['player_id']));
        $best = $ratings[0];
        $playerClubMap = [];
        foreach (array_slice($homeSquad, 0, 11) as $p) $playerClubMap[(int)$p['id']] = (int)$match['home_club_id'];
        foreach (array_slice($awaySquad, 0, 11) as $p) $playerClubMap[(int)$p['id']] = (int)$match['away_club_id'];
        $clubId = (int)($playerClubMap[(int)$best['player_id']] ?? 0);
        $seasonId = (int)($match['season_id'] ?? 0);
        if ($seasonId <= 0 || $clubId <= 0) return;
        $season = $this->db->fetchOne("SELECT competition_id FROM seasons WHERE id = ?", [$seasonId]);
        if (!$season) return;
        $competitionId = (int)$season['competition_id'];
        $week = (int)($match['week'] ?? 0);

        $this->history->recordPlayerOfMatch((int)$match['id'], $seasonId, $competitionId, [
            'player_id' => (int)$best['player_id'],
            'club_id' => $clubId,
            'rating' => (float)$best['rating']
        ]);
        if ($week > 0) {
            $this->history->upsertWeeklyAwardFromMatch((int)$match['id'], $seasonId, $competitionId, $week, [
                'player_id' => (int)$best['player_id'],
                'club_id' => $clubId,
                'score' => (float)$best['rating']
            ]);
        }
    }

    private function claimScheduledMatch(int $matchId): array {
        $this->db->beginTransaction();
        try {
            $match = $this->db->fetchOne("SELECT * FROM matches WHERE id = ? FOR UPDATE", [$matchId]);
            if (!$match || $match['status'] !== 'SCHEDULED') {
                $this->db->rollBack();
                throw new Exception("Match not available for simulation");
            }

            $this->db->query("UPDATE matches SET status = 'LIVE' WHERE id = ? AND status = 'SCHEDULED'", [$matchId]);
            $this->db->commit();

            return $match;
        } catch (Throwable $e) {
            if (method_exists($this->db, 'rollBack')) {
                try { $this->db->rollBack(); } catch (Throwable $ignored) {}
            }
            throw $e;
        }
    }

    // ─── محاسبه قدرت تیم ───────────────────────────────────────────────────

    private function getSquad(int $clubId, ?int $matchId = null): array {
        if ($matchId !== null) {
            $lockedCount = $this->db->fetchOne(
                "SELECT COUNT(*) AS total FROM match_lineups WHERE match_id = ? AND club_id = ? AND is_starter = 1",
                [$matchId, $clubId]
            );
            if ((int)($lockedCount['total'] ?? 0) >= 11) {
                return $this->db->fetchAll(
                    "SELECT p.*,
                            GROUP_CONCAT(a.code SEPARATOR ',') as abilities,
                            COALESCE(ml.position, p.position) AS lineup_position,
                            COALESCE(ml.is_starter, 0) AS lineup_is_starter
                     FROM players p
                     LEFT JOIN match_lineups ml ON ml.player_id = p.id AND ml.match_id = ? AND ml.club_id = ?
                     LEFT JOIN player_abilities pa ON p.id = pa.player_id AND pa.is_active = 1
                     LEFT JOIN abilities a ON pa.ability_id = a.id
                     WHERE p.club_id = ? AND p.is_injured = 0 AND p.is_retired = 0
                     GROUP BY p.id, ml.position, ml.is_starter
                     ORDER BY COALESCE(ml.is_starter, 0) DESC, p.overall DESC
                     LIMIT 18",
                    [$matchId, $clubId, $clubId]
                );
            }
        }

        return $this->db->fetchAll(
            "SELECT p.*, 
                    GROUP_CONCAT(a.code SEPARATOR ',') as abilities,
                    p.position AS lineup_position
             FROM players p
             LEFT JOIN player_abilities pa ON p.id = pa.player_id AND pa.is_active = 1
             LEFT JOIN abilities a ON pa.ability_id = a.id
             WHERE p.club_id = ? AND p.is_injured = 0 AND p.is_retired = 0
             GROUP BY p.id
             ORDER BY p.overall DESC
             LIMIT 18",
            [$clubId]
        );
    }

    private function getTactics(int $clubId): array {
        $tactics = $this->db->fetchOne("SELECT * FROM tactics WHERE club_id = ?", [$clubId]);
        return $tactics ?: [
            'formation' => '4-3-3',
            'style' => 'BALANCED',
            'mentality' => 'NORMAL',
            'pressing' => 5,
            'tempo' => 5,
            'width' => 5
        ];
    }

    private function calculateTeamStrength(array $squad, array $tactics): array {
        $starters = array_slice($squad, 0, 11);

        $attack = $defense = $midfield = 0;
        $attackCount = $defCount = $midCount = 0;

        foreach ($starters as $player) {
            $effective = $this->effectiveOverall($player);
            $pos = (string)($player['lineup_position'] ?? $player['position']);

            if (in_array($pos, ['ST', 'CF', 'LW', 'RW', 'CAM'])) {
                $attack += $effective;
                $attackCount++;
            } elseif (in_array($pos, ['CB', 'LB', 'RB', 'LWB', 'RWB', 'GK'])) {
                $defense += $effective;
                $defCount++;
            } else {
                $midfield += $effective;
                $midCount++;
            }
        }

        $attack = $attackCount > 0 ? $attack / $attackCount : 60;
        $defense = $defCount > 0 ? $defense / $defCount : 60;
        $midfield = $midCount > 0 ? $midfield / $midCount : 60;

        // تأثیر تاکتیک
        $tacticBonus = match($tactics['style']) {
            'ATTACKING' => ['attack' => 1.10, 'defense' => 0.92, 'mid' => 1.0],
            'DEFENSIVE' => ['attack' => 0.90, 'defense' => 1.12, 'mid' => 1.0],
            'PRESSING'  => ['attack' => 1.05, 'defense' => 1.05, 'mid' => 1.08],
            'COUNTER'   => ['attack' => 1.08, 'defense' => 1.05, 'mid' => 0.98],
            'POSSESSION'=> ['attack' => 1.02, 'defense' => 1.02, 'mid' => 1.12],
            default     => ['attack' => 1.0, 'defense' => 1.0, 'mid' => 1.0],
        };

        return [
            'attack'  => $attack * $tacticBonus['attack'],
            'defense' => $defense * $tacticBonus['defense'],
            'midfield'=> $midfield * $tacticBonus['mid'],
            'overall' => ($attack + $defense + $midfield) / 3,
            'style'   => $tactics['style']
        ];
    }

    private function effectiveOverall(array $player): float {
        $base = $player['overall'];

        // تأثیر فرم (۵ تا ۱۰ → ضریب ۰.۹۵ تا ۱.۰۵)
        $formMod = 0.95 + (($player['form'] - 5) / 100);

        // تأثیر خستگی (۰ تا ۱۰۰ → ضریب ۱.۰ تا ۰.۸۵)
        $fatigueMod = 1.0 - ($player['fatigue'] / 667);

        // تأثیر روحیه
        $moraleMod = 0.97 + (($player['morale'] - 5) / 150);

        // Ability bonuses
        $abilityMod = $this->getAbilityOverallBonus($player);

        return $base * $formMod * $fatigueMod * $moraleMod * $abilityMod;
    }

    private function getAbilityOverallBonus(array $player): float {
        $abilities = array_filter(explode(',', $player['abilities'] ?? ''));
        $bonus = 1.0;

        foreach ($abilities as $ability) {
            $bonus += match(trim($ability)) {
                'CLINICAL_FINISHER' => 0.03,
                'LIGHTNING_PACE'    => 0.02,
                'AERIAL_THREAT'     => 0.02,
                'IRON_WALL'         => 0.03,
                'PLAYMAKER'         => 0.025,
                'VETERAN'           => 0.02,
                'POACHER'           => 0.025,
                'CAPTAIN_LEADER'    => 0.015,
                default             => 0.0
            };
        }

        return $bonus;
    }

    // ─── محاسبه xG ─────────────────────────────────────────────────────────

    private function calculateXG(array $home, array $away, array $homeTactics, array $awayTactics): array {
        // نسبت حمله به دفاع
        $homeAttackRatio = $home['attack'] / max($away['defense'], 1);
        $awayAttackRatio = $away['attack'] / max($home['defense'], 1);

        // xG پایه
        $homeXG = 1.2 * $homeAttackRatio * self::HOME_ADVANTAGE;
        $awayXG = 1.2 * $awayAttackRatio;

        // تأثیر تاکتیک‌ها روی هم
        $homeStyle = $homeTactics['style'];
        $awayStyle = $awayTactics['style'];

        $homeTacticMod = self::TACTIC_MATRIX[$homeStyle][$awayStyle] ?? 1.0;
        $awayTacticMod = self::TACTIC_MATRIX[$awayStyle][$homeStyle] ?? 1.0;

        $homeXG *= $homeTacticMod;
        $awayXG *= $awayTacticMod;

        // محدود کردن به بازه منطقی
        return [
            'home' => max(0.3, min(4.5, $homeXG)),
            'away' => max(0.3, min(4.5, $awayXG))
        ];
    }

    // ─── توزیع پوآسون ──────────────────────────────────────────────────────

    private function poissonRandom(float $lambda): int {
        $L = exp(-$lambda);
        $k = 0;
        $p = 1.0;

        do {
            $k++;
            $p *= (mt_rand() / mt_getrandmax());
        } while ($p > $L);

        return $k - 1;
    }

    // ─── تولید رویدادها ────────────────────────────────────────────────────

    private function generateEvents(int $matchId, array $match, array $homeSquad, array $awaySquad, int $homeGoals, int $awayGoals): array {
        $events = [];

        // گل‌های خانه
        $events = array_merge($events, $this->generateGoalEvents($matchId, $homeGoals, $homeSquad, 'HOME'));
        // گل‌های مهمان
        $events = array_merge($events, $this->generateGoalEvents($matchId, $awayGoals, $awaySquad, 'AWAY'));
        // کارت‌ها
        $events = array_merge($events, $this->generateCardEvents($matchId, $homeSquad, 'HOME'));
        $events = array_merge($events, $this->generateCardEvents($matchId, $awaySquad, 'AWAY'));
        // مصدومیت‌ها
        $events = array_merge($events, $this->generateInjuryEvents($matchId, $homeSquad, 'HOME'));
        $events = array_merge($events, $this->generateInjuryEvents($matchId, $awaySquad, 'AWAY'));

        // مرتب‌سازی بر اساس دقیقه
        usort($events, fn($a, $b) => $a['minute'] <=> $b['minute']);

        return $events;
    }

    private function generateGoalEvents(int $matchId, int $goals, array $squad, string $team): array {
        $events = [];
        $starters = array_slice($squad, 0, 11);

        for ($i = 0; $i < $goals; $i++) {
            $scorer = $this->weightedPlayerPick($starters, 'shooting');
            $assister = $this->weightedPlayerPick($starters, 'passing', $scorer['id']);
            $minute = mt_rand(1, 90);

            $events[] = [
                'match_id' => $matchId,
                'minute' => $minute,
                'type' => 'GOAL',
                'team' => $team,
                'player_id' => $scorer['id'],
                'assist_player_id' => (mt_rand(1, 100) <= 70) ? $assister['id'] : null,
                'details' => json_encode(['scorer_name' => $scorer['first_name'] . ' ' . $scorer['last_name']])
            ];
        }

        return $events;
    }

    private function generateCardEvents(int $matchId, array $squad, string $team): array {
        $events = [];
        $starters = array_slice($squad, 0, 11);

        foreach ($starters as $player) {
            if ((mt_rand() / mt_getrandmax()) < self::YELLOW_CARD_CHANCE) {
                $minute = mt_rand(1, 90);
                $events[] = [
                    'match_id' => $matchId,
                    'minute' => $minute,
                    'type' => 'YELLOW_CARD',
                    'team' => $team,
                    'player_id' => $player['id'],
                    'assist_player_id' => null,
                    'details' => json_encode([])
                ];

                // کارت قرمز دوم
                if ((mt_rand() / mt_getrandmax()) < 0.08) {
                    $events[] = [
                        'match_id' => $matchId,
                        'minute' => min(90, $minute + mt_rand(5, 30)),
                        'type' => 'SECOND_YELLOW',
                        'team' => $team,
                        'player_id' => $player['id'],
                        'assist_player_id' => null,
                        'details' => json_encode([])
                    ];
                }
            }

            // کارت قرمز مستقیم
            if ((mt_rand() / mt_getrandmax()) < self::RED_CARD_CHANCE) {
                $events[] = [
                    'match_id' => $matchId,
                    'minute' => mt_rand(1, 90),
                    'type' => 'RED_CARD',
                    'team' => $team,
                    'player_id' => $player['id'],
                    'assist_player_id' => null,
                    'details' => json_encode([])
                ];
            }
        }

        return $events;
    }

    private function generateInjuryEvents(int $matchId, array $squad, string $team): array {
        $events = [];
        $starters = array_slice($squad, 0, 11);

        foreach ($starters as $player) {
            if ((mt_rand() / mt_getrandmax()) < self::INJURY_CHANCE) {
                $events[] = [
                    'match_id' => $matchId,
                    'minute' => mt_rand(1, 90),
                    'type' => 'INJURY',
                    'team' => $team,
                    'player_id' => $player['id'],
                    'assist_player_id' => null,
                    'details' => json_encode(['severity' => mt_rand(3, 21)])
                ];
            }
        }

        return $events;
    }

    private function weightedPlayerPick(array $players, string $attribute, ?int $excludeId = null): array {
        $pool = array_filter($players, fn($p) => $p['id'] !== $excludeId);
        if (empty($pool)) $pool = $players;

        $totalWeight = array_sum(array_column($pool, $attribute));
        $rand = mt_rand(1, max(1, (int)$totalWeight));
        $cumulative = 0;

        foreach ($pool as $player) {
            $cumulative += $player[$attribute];
            if ($rand <= $cumulative) return $player;
        }

        return end($pool);
    }

    // ─── آمار بازی ─────────────────────────────────────────────────────────

    private function generateStats(array $home, array $away, int $homeGoals, int $awayGoals): array {
        $totalStrength = $home['overall'] + $away['overall'];
        $homePoss = (int)(($home['midfield'] / ($home['midfield'] + $away['midfield'])) * 100);

        return [
            'possession' => ['home' => $homePoss, 'away' => 100 - $homePoss],
            'shots' => [
                'home' => $homeGoals + mt_rand(3, 10),
                'away' => $awayGoals + mt_rand(2, 8)
            ],
            'shots_on_target' => [
                'home' => $homeGoals + mt_rand(1, 4),
                'away' => $awayGoals + mt_rand(1, 3)
            ],
            'corners' => [
                'home' => mt_rand(2, 10),
                'away' => mt_rand(1, 8)
            ],
            'fouls' => [
                'home' => mt_rand(8, 18),
                'away' => mt_rand(8, 18)
            ]
        ];
    }

    // ─── ریتینگ بازیکنان ───────────────────────────────────────────────────

    private function calculateRatings(array $homeSquad, array $awaySquad, array $events, int $homeGoals, int $awayGoals): array {
        $ratings = [];
        $goalScorers = [];
        $assisters = [];
        $yellowCards = [];
        $redCards = [];
        $injured = [];

        foreach ($events as $event) {
            match($event['type']) {
                'GOAL' => $goalScorers[$event['player_id']] = ($goalScorers[$event['player_id']] ?? 0) + 1,
                'YELLOW_CARD', 'SECOND_YELLOW' => $yellowCards[$event['player_id']] = true,
                'RED_CARD' => $redCards[$event['player_id']] = true,
                'INJURY' => $injured[$event['player_id']] = true,
                default => null
            };
            if (!empty($event['assist_player_id'])) {
                $assisters[$event['assist_player_id']] = ($assisters[$event['assist_player_id']] ?? 0) + 1;
            }
        }

        $allPlayers = array_merge(
            array_map(fn($p) => [...$p, 'team' => 'HOME', 'result' => $homeGoals > $awayGoals ? 'W' : ($homeGoals < $awayGoals ? 'L' : 'D')], array_slice($homeSquad, 0, 11)),
            array_map(fn($p) => [...$p, 'team' => 'AWAY', 'result' => $awayGoals > $homeGoals ? 'W' : ($awayGoals < $homeGoals ? 'L' : 'D')], array_slice($awaySquad, 0, 11))
        );

        foreach ($allPlayers as $player) {
            $base = 6.0;

            // گل
            $base += ($goalScorers[$player['id']] ?? 0) * 1.5;
            // آسیست
            $base += ($assisters[$player['id']] ?? 0) * 0.8;
            // نتیجه
            $base += match($player['result']) {
                'W' => 0.5,
                'D' => 0.0,
                'L' => -0.3,
            };
            // کارت
            if (isset($yellowCards[$player['id']])) $base -= 0.3;
            if (isset($redCards[$player['id']])) $base -= 1.5;
            // مصدومیت
            if (isset($injured[$player['id']])) $base -= 0.2;

            // محدود به ۱-۱۰
            $rating = max(1.0, min(10.0, $base));

            $ratings[] = [
                'player_id' => $player['id'],
                'rating' => round($rating, 1),
                'goals' => $goalScorers[$player['id']] ?? 0,
                'assists' => $assisters[$player['id']] ?? 0,
                'result' => $player['result'],
                'yellow_cards' => isset($yellowCards[$player['id']]) ? 1 : 0,
                'red_cards' => isset($redCards[$player['id']]) ? 1 : 0,
                'injured' => isset($injured[$player['id']]) ? 1 : 0
            ];
        }

        return $ratings;
    }

    // ─── ذخیره نتیجه ───────────────────────────────────────────────────────

    private function saveResult(int $matchId, int $homeGoals, int $awayGoals, array $stats, array $xG, array $events, array $ratings): void {
        $this->db->beginTransaction();

        try {
            // آپدیت نتیجه
            $this->db->query(
                "UPDATE matches
                 SET home_score = ?,
                     away_score = ?,
                     status = 'FINISHED',
                     stats = ?,
                     home_xg = ?,
                     away_xg = ?,
                     played_at = NOW()
                 WHERE id = ?",
                [$homeGoals, $awayGoals, json_encode($stats), $xG['home'], $xG['away'], $matchId]
            );

            // ذخیره رویدادها
            foreach ($events as $event) {
                $this->db->insert('match_events', $event);
            }

            // ذخیره ریتینگ‌ها
            foreach ($ratings as $rating) {
                $this->db->insert('player_match_ratings', [
                    'match_id' => $matchId,
                    'player_id' => $rating['player_id'],
                    'rating' => $rating['rating'],
                    'goals' => $rating['goals'],
                    'assists' => $rating['assists']
                ]);
            }

            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    // ─── آپدیت وضعیت بازیکنان ──────────────────────────────────────────────

    private function updatePlayerStates(array $match, array $homeSquad, array $awaySquad, array $events, array $ratings): void {
        $starters = array_merge(array_slice($homeSquad, 0, 11), array_slice($awaySquad, 0, 11));
        $benches = array_merge(array_slice($homeSquad, 11, 7), array_slice($awaySquad, 11, 7));
        $allPlayers = array_merge($starters, $benches);
        $playedAt = date('Y-m-d H:i:s');
        $seasonId = (int)($match['season_id'] ?? $this->getCurrentSeasonId());
        $ratingByPlayer = [];
        foreach ($ratings as $r) {
            $ratingByPlayer[(int)$r['player_id']] = $r;
        }

        foreach ($allPlayers as $player) {
            $playerId = $player['id'];
            $rating = $ratingByPlayer[$playerId] ?? null;
            $started = $rating !== null;
            $minutesPlayed = $started ? 90 : 0;

            // مصدومیت
            $injury = current(array_filter($events, fn($e) => $e['type'] === 'INJURY' && $e['player_id'] === $playerId));
            if ($injury) {
                $details = json_decode($injury['details'], true);
                $this->db->insert('injuries', [
                    'player_id' => $playerId,
                    'type' => $this->randomInjuryType(),
                    'severity' => $details['severity'],
                    'match_id' => $injury['match_id'] ?? null,
                    'recovered_at' => date('Y-m-d H:i:s', strtotime("+{$details['severity']} days"))
                ]);
                $this->db->query("UPDATE players SET is_injured = 1, injury_days = ? WHERE id = ?", [$details['severity'], $playerId]);
            }

            $this->playerCareer->applyPostMatchPlayerUpdate($player, $rating, $started, $minutesPlayed, $injury !== false, $playedAt);

            // آپدیت آمار فصل + تاریخچه حرفه‌ای
            $this->updateSeasonStats($playerId, $seasonId, $rating, $minutesPlayed, $started);
            $this->playerCareer->upsertCareerHistoryFromSeasonStats($playerId, $seasonId);

            // بررسی باز شدن Ability
            $this->checkAbilityUnlock($playerId);
        }
    }

    private function updateSeasonStats(int $playerId, int $seasonId, ?array $rating, int $minutesPlayed, bool $started): void {
        if (!$rating) return;

        $existing = $this->db->fetchOne(
            "SELECT * FROM player_season_stats WHERE player_id = ? AND season_id = ?",
            [$playerId, $seasonId]
        );

        if ($existing) {
            $this->db->query(
                "UPDATE player_season_stats 
                 SET appearances = appearances + 1,
                     starts = starts + :starts,
                     minutes_played = minutes_played + :minutes_played,
                     goals = goals + :goals,
                     assists = assists + :assists,
                     yellow_cards = yellow_cards + :yellow_cards,
                     red_cards = red_cards + :red_cards,
                     avg_rating = ((avg_rating * appearances) + :rating) / (appearances + 1)
                 WHERE player_id = :pid AND season_id = :sid",
                [
                    'starts' => $started ? 1 : 0,
                    'minutes_played' => $minutesPlayed,
                    'goals' => $rating['goals'],
                    'assists' => $rating['assists'],
                    'yellow_cards' => (int)($rating['yellow_cards'] ?? 0),
                    'red_cards' => (int)($rating['red_cards'] ?? 0),
                    'rating' => $rating['rating'],
                    'pid' => $playerId,
                    'sid' => $seasonId
                ]
            );
        } else {
            $this->db->insert('player_season_stats', [
                'player_id' => $playerId,
                'season_id' => $seasonId,
                'club_id' => $this->getPlayerClubId($playerId),
                'appearances' => 1,
                'starts' => $started ? 1 : 0,
                'minutes_played' => $minutesPlayed,
                'goals' => $rating['goals'],
                'assists' => $rating['assists'],
                'yellow_cards' => (int)($rating['yellow_cards'] ?? 0),
                'red_cards' => (int)($rating['red_cards'] ?? 0),
                'avg_rating' => $rating['rating']
            ]);
        }
    }

    private function checkAbilityUnlock(int $playerId): void {
        $stats = $this->db->fetchOne(
            "SELECT SUM(goals) as total_goals, SUM(appearances) as total_apps
             FROM player_season_stats WHERE player_id = ?",
            [$playerId]
        );

        $unlocks = [];

        // POACHER: 80+ گل
        if ($stats['total_goals'] >= 80) $unlocks[] = 'POACHER';
        // VETERAN: 200+ بازی
        if ($stats['total_apps'] >= 200) $unlocks[] = 'VETERAN';

        foreach ($unlocks as $code) {
            $ability = $this->db->fetchOne("SELECT id FROM abilities WHERE code = ?", [$code]);
            if ($ability) {
                $exists = $this->db->fetchOne(
                    "SELECT id FROM player_abilities WHERE player_id = ? AND ability_id = ?",
                    [$playerId, $ability['id']]
                );
                if (!$exists) {
                    $this->db->insert('player_abilities', [
                        'player_id' => $playerId,
                        'ability_id' => $ability['id'],
                        'is_active' => 1
                    ]);
                }
            }
        }
    }

    // ─── آپدیت جدول ─────────────────────────────────────────────────────────

    private function updateStandings(array $match, int $homeGoals, int $awayGoals): void {
        $seasonId = $this->getCurrentSeasonId();

        $homeStanding = $this->db->fetchOne(
            "SELECT * FROM standings WHERE season_id = ? AND club_id = ?",
            [$seasonId, $match['home_club_id']]
        );
        $awayStanding = $this->db->fetchOne(
            "SELECT * FROM standings WHERE season_id = ? AND club_id = ?",
            [$seasonId, $match['away_club_id']]
        );

        // خانه
        $homeUpdate = [
            'played' => $homeStanding['played'] + 1,
            'goals_for' => $homeStanding['goals_for'] + $homeGoals,
            'goals_against' => $homeStanding['goals_against'] + $awayGoals
        ];
        if ($homeGoals > $awayGoals) {
            $homeUpdate['won'] = $homeStanding['won'] + 1;
            $homeUpdate['points'] = $homeStanding['points'] + 3;
        } elseif ($homeGoals === $awayGoals) {
            $homeUpdate['drawn'] = $homeStanding['drawn'] + 1;
            $homeUpdate['points'] = $homeStanding['points'] + 1;
        } else {
            $homeUpdate['lost'] = $homeStanding['lost'] + 1;
        }

        // مهمان
        $awayUpdate = [
            'played' => $awayStanding['played'] + 1,
            'goals_for' => $awayStanding['goals_for'] + $awayGoals,
            'goals_against' => $awayStanding['goals_against'] + $homeGoals
        ];
        if ($awayGoals > $homeGoals) {
            $awayUpdate['won'] = $awayStanding['won'] + 1;
            $awayUpdate['points'] = $awayStanding['points'] + 3;
        } elseif ($awayGoals === $homeGoals) {
            $awayUpdate['drawn'] = $awayStanding['drawn'] + 1;
            $awayUpdate['points'] = $awayStanding['points'] + 1;
        } else {
            $awayUpdate['lost'] = $awayStanding['lost'] + 1;
        }

        $this->persistStanding($homeStanding['id'], $homeUpdate);
        $this->persistStanding($awayStanding['id'], $awayUpdate);
    }

    private function getCurrentSeasonId(): int {
        $season = $this->db->fetchOne("SELECT id FROM seasons WHERE status = 'ACTIVE' LIMIT 1");
        return $season['id'] ?? 1;
    }

    private function getPlayerClubId(int $playerId): int {
        $player = $this->db->fetchOne("SELECT club_id FROM players WHERE id = ?", [$playerId]);
        return (int)($player['club_id'] ?? 0);
    }

    private function persistStanding(int $standingId, array $update): void {
        $goalDiff = (int)$update['goals_for'] - (int)$update['goals_against'];
        $this->db->query(
            "UPDATE standings
             SET played = ?, won = ?, drawn = ?, lost = ?,
                 goals_for = ?, goals_against = ?, goal_diff = ?, points = ?
             WHERE id = ?",
            [
                $update['played'],
                $update['won'] ?? 0,
                $update['drawn'] ?? 0,
                $update['lost'] ?? 0,
                $update['goals_for'],
                $update['goals_against'],
                $goalDiff,
                $update['points'] ?? 0,
                $standingId
            ]
        );
    }

    private function randomInjuryType(): string {
        $types = ['MUSCLE', 'KNEE', 'ANKLE', 'HAMSTRING', 'BACK', 'SHOULDER', 'CONCUSSION'];
        return $types[array_rand($types)];
    }
}
