<?php
// app/Services/DailyCycleOrchestrator.php

class DailyCycleOrchestrator {
    private Database $db;
    private MatchEngine $engine;
    private AIClubManagementService $aiClubManager;
    private array $phases;

    public function __construct(?MatchEngine $engine = null, ?array $config = null, ?Database $db = null) {
        $this->db = $db ?? Database::getInstance();
        $this->engine = $engine ?? new MatchEngine();
        $this->aiClubManager = new AIClubManagementService($this->db);
        $allConfig = $config ?? require __DIR__ . '/../../config/config.php';
        $this->phases = $allConfig['game']['daily_cycle']['phases'];
    }

    public function run(?\DateTimeImmutable $now = null): array {
        $at = $now ?? new \DateTimeImmutable('now');
        $cycleDate = $at->format('Y-m-d');
        $worldPhase = $this->detectPhase($at);

        $scheduled = $this->db->fetchAll(
            "SELECT m.* FROM matches m
             WHERE m.status = 'SCHEDULED'
               AND DATE(m.scheduled_at) = ?
               AND m.scheduled_at <= ?
             ORDER BY m.scheduled_at ASC",
            [$cycleDate, $at->format('Y-m-d H:i:s')]
        );

        $states = $this->initializeClubStates($cycleDate);
        $vacancySync = $this->aiClubManager->syncVacancyStatesForAllClubs();
        $career = new PlayerCareerService($this->db);
        $career->applyDailyRecoveryAndDrift();
        $development = $career->runDailyDevelopmentAndValuation($cycleDate);
        $finance = new FinanceService($this->db);
        $salaryPosting = $finance->postCoachSalariesForCycle($cycleDate);
        $facilities = new ClubFacilityService($this->db);
        $facilityMaintenance = $facilities->postDailyMaintenance($cycleDate);

        $result = [
            'executed_at' => $at->format('Y-m-d H:i:s'),
            'world_phase' => $worldPhase['key'] ?? 'OFF_HOURS',
            'simulated' => [],
            'failed' => [],
            'skipped' => [],
            'locks' => [],
            'club_states' => [],
            'ai_preparation' => [],
            'salary_postings' => $salaryPosting['posted'] ?? 0,
            'facility_maintenance_postings' => $facilityMaintenance['posted'] ?? 0,
            'vacancy_sync' => $vacancySync['synced'] ?? 0,
            'development_adjustments' => $development['adjusted'] ?? 0,
        ];

        foreach ($states as $state) {
            $profile = self::profileKey((int)$state['matches_today']);
            $current = $this->advanceClubPhase($state, $worldPhase['key'] ?? null, $profile);
            $clubId = (int)$state['club_id'];
            $result['club_states'][] = [
                'club_id' => $clubId,
                'matches_today' => (int)$state['matches_today'],
                'phase' => $current,
            ];

            $prep = $this->aiClubManager->applyDailyPreparation($clubId, $cycleDate);
            $result['ai_preparation'][] = ['club_id' => $clubId, 'mode' => $prep['mode'] ?? 'none'];
        }

        foreach ($scheduled as $match) {
            $matchId = (int)$match['id'];
            $matchPhase = self::phaseForMatchTime((string)$match['scheduled_at']);

            $homeState = $this->getClubState($cycleDate, (int)$match['home_club_id']);
            $awayState = $this->getClubState($cycleDate, (int)$match['away_club_id']);

            if (!$homeState || !$awayState) {
                $result['skipped'][] = ['match_id' => $matchId, 'reason' => 'missing_club_state'];
                continue;
            }

            if (($homeState['current_phase_key'] ?? '') !== $matchPhase || ($awayState['current_phase_key'] ?? '') !== $matchPhase) {
                $result['skipped'][] = ['match_id' => $matchId, 'reason' => 'club_phase_mismatch', 'expected' => $matchPhase];
                continue;
            }

            try {
                $this->lockLineupsForMatch($matchId);
                $result['locks'][] = $matchId;
                $result['simulated'][] = $this->engine->simulate($matchId);
            } catch (\Throwable $e) {
                $result['failed'][] = ['match_id' => $matchId, 'error' => $e->getMessage()];
            }
        }

        $this->snapshot($at, $result);
        return $result;
    }

    public function detectPhase(\DateTimeImmutable $now): ?array {
        $time = $now->format('H:i');
        foreach ($this->phases['two_matches'] as $phase) {
            if (self::isTimeInRange($time, $phase['start'], $phase['end'])) {
                return $phase;
            }
        }
        return null;
    }

    public static function profileKey(int $matchesToday): string {
        return $matchesToday >= 2 ? 'two_matches' : 'one_match';
    }

    public static function phaseForMatchTime(string $scheduledAt): string {
        $hour = (int)date('H', strtotime($scheduledAt));
        return $hour < 15 ? 'MATCH_1_LIVE' : 'MATCH_2_LIVE';
    }

    public static function isTimeInRange(string $time, string $start, string $end): bool {
        return $time >= $start && $time <= $end;
    }

    public static function validateLineupRows(array $rows): array {
        if (count($rows) < 11) {
            return ['ok' => false, 'reason' => 'lineup_needs_minimum_11_players'];
        }

        $players = [];
        $hasGk = false;
        foreach ($rows as $row) {
            $pid = (int)$row['player_id'];
            if (isset($players[$pid])) {
                return ['ok' => false, 'reason' => 'duplicate_player_in_lineup'];
            }
            $players[$pid] = true;

            $slot = (string)$row['position_slot'];
            $position = (string)($row['actual_position'] ?? '');
            if ($slot === 'GK' && $position === 'GK') {
                $hasGk = true;
            }
        }

        if (!$hasGk) {
            return ['ok' => false, 'reason' => 'lineup_missing_goalkeeper'];
        }

        return ['ok' => true, 'reason' => null];
    }

    private function initializeClubStates(string $cycleDate): array {
        $rows = $this->db->fetchAll(
            "SELECT club_id, COUNT(*) AS matches_today
             FROM (
                SELECT home_club_id AS club_id FROM matches WHERE DATE(scheduled_at) = ?
                UNION ALL
                SELECT away_club_id AS club_id FROM matches WHERE DATE(scheduled_at) = ?
             ) x
             GROUP BY club_id",
            [$cycleDate, $cycleDate]
        );

        foreach ($rows as $row) {
            $clubId = (int)$row['club_id'];
            $matchesToday = (int)$row['matches_today'];
            $exists = $this->getClubState($cycleDate, $clubId);
            if (!$exists) {
                $this->db->insert('club_daily_cycle_states', [
                    'cycle_date' => $cycleDate,
                    'club_id' => $clubId,
                    'matches_today' => $matchesToday,
                    'profile_key' => self::profileKey($matchesToday),
                    'current_phase_key' => 'LINEUP_1_SETUP',
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }
        }

        return $this->db->fetchAll(
            "SELECT * FROM club_daily_cycle_states WHERE cycle_date = ? ORDER BY club_id ASC",
            [$cycleDate]
        );
    }

    private function getClubState(string $cycleDate, int $clubId): ?array {
        return $this->db->fetchOne(
            "SELECT * FROM club_daily_cycle_states WHERE cycle_date = ? AND club_id = ?",
            [$cycleDate, $clubId]
        );
    }

    private function advanceClubPhase(array $state, ?string $worldPhaseKey, string $profile): string {
        if (!$worldPhaseKey) {
            return (string)$state['current_phase_key'];
        }

        $allowed = array_map(fn($p) => $p['key'], $this->phases[$profile]);
        if (!in_array($worldPhaseKey, $allowed, true)) {
            return (string)$state['current_phase_key'];
        }

        $this->db->execute(
            "UPDATE club_daily_cycle_states SET current_phase_key = ?, updated_at = NOW() WHERE id = ?",
            [$worldPhaseKey, (int)$state['id']]
        );

        return $worldPhaseKey;
    }

    private function lockLineupsForMatch(int $matchId): void {
        $match = $this->db->fetchOne(
            "SELECT id, home_club_id, away_club_id, scheduled_at FROM matches WHERE id = ?",
            [$matchId]
        );
        if (!$match) return;

        $lineupPhase = self::phaseForMatchTime((string)$match['scheduled_at']) === 'MATCH_1_LIVE' ? 'MATCH_1' : 'MATCH_2';

        foreach ([(int)$match['home_club_id'], (int)$match['away_club_id']] as $clubId) {
            $rows = $this->db->fetchAll(
                "SELECT tl.player_id, tl.position_slot, tl.slot_order, p.position AS actual_position
                 FROM tactic_lineups tl
                 JOIN players p ON p.id = tl.player_id
                 WHERE tl.club_id = ? AND tl.phase_key IN (?, 'MATCH_1') AND tl.is_active = 1
                 ORDER BY CASE WHEN tl.phase_key = ? THEN 0 ELSE 1 END, tl.position_slot, tl.slot_order, tl.id",
                [$clubId, $lineupPhase, $lineupPhase]
            );

            $validation = self::validateLineupRows($rows);
            if (!$validation['ok']) {
                $aiFix = $this->aiClubManager->ensureLineupForMatchPhase($clubId, $lineupPhase);
                if (!($aiFix['ok'] ?? false)) {
                    throw new RuntimeException('Invalid lineup for club ' . $clubId . ': ' . $validation['reason']);
                }

                $rows = $this->db->fetchAll(
                    "SELECT tl.player_id, tl.position_slot, tl.slot_order, p.position AS actual_position
                     FROM tactic_lineups tl
                     JOIN players p ON p.id = tl.player_id
                     WHERE tl.club_id = ? AND tl.phase_key IN (?, 'MATCH_1') AND tl.is_active = 1
                     ORDER BY CASE WHEN tl.phase_key = ? THEN 0 ELSE 1 END, tl.position_slot, tl.slot_order, tl.id",
                    [$clubId, $lineupPhase, $lineupPhase]
                );
                $validation = self::validateLineupRows($rows);
                if (!$validation['ok']) {
                    throw new RuntimeException('Invalid lineup for club ' . $clubId . ': ' . $validation['reason']);
                }
            }

            $seen = [];
            $inserted = 0;
            foreach ($rows as $row) {
                $playerId = (int)$row['player_id'];
                if (isset($seen[$playerId])) continue;
                $seen[$playerId] = true;

                if ($inserted >= 11) break;

                $exists = $this->db->fetchOne(
                    "SELECT id FROM match_lineups WHERE match_id = ? AND player_id = ?",
                    [$matchId, $playerId]
                );
                if ($exists) continue;

                $this->db->insert('match_lineups', [
                    'match_id' => $matchId,
                    'club_id' => $clubId,
                    'player_id' => $playerId,
                    'position' => $row['position_slot'],
                    'is_starter' => 1,
                    'shirt_number' => 0,
                ]);
                $inserted++;
            }

            if ($inserted < 11) {
                throw new RuntimeException('Unable to lock 11 starters for club ' . $clubId);
            }
        }
    }

    private function snapshot(\DateTimeImmutable $at, array $result): void {
        $this->db->insert('daily_cycle_snapshots', [
            'cycle_date' => $at->format('Y-m-d'),
            'phase_key' => $result['world_phase'],
            'executed_at' => $at->format('Y-m-d H:i:s'),
            'matches_simulated' => count($result['simulated']),
            'payload' => json_encode($result, JSON_UNESCAPED_UNICODE),
        ]);
    }
}
