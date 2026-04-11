<?php
// app/Services/AdminCompetitionService.php

class AdminCompetitionService {
    private Database $db;

    private const ENTRY_TYPES = ['direct', 'promoted', 'relegated', 'champion', 'qualified', 'wildcard'];

    public function __construct(?Database $db = null) {
        $this->db = $db ?? Database::getInstance();
        $this->ensureRolloverTable();
    }

    public function listCompetitionsWithSeasons(): array {
        $competitions = $this->db->fetchAll(
            "SELECT c.*, p.name AS parent_name
             FROM competitions c
             LEFT JOIN competitions p ON c.parent_competition_id = p.id
             ORDER BY c.type, c.level, c.name"
        );

        foreach ($competitions as &$c) {
            $c['seasons'] = $this->db->fetchAll(
                "SELECT * FROM seasons WHERE competition_id = ? ORDER BY start_date DESC",
                [(int)$c['id']]
            );

            foreach ($c['seasons'] as &$season) {
                $sid = (int)$season['id'];
                $season['participants'] = $this->getSeasonParticipants($sid);
                $season['rollover'] = $this->getSeasonRolloverReadiness($sid);
            }
        }

        return $competitions;
    }

    public function listClubs(): array {
        return $this->db->fetchAll("SELECT id, name FROM clubs ORDER BY name ASC");
    }

    public static function entryTypes(): array {
        return self::ENTRY_TYPES;
    }

    public function createCompetition(array $data): array {
        $name = trim((string)($data['name'] ?? ''));
        $type = trim((string)($data['type'] ?? 'LEAGUE'));
        $teamsCount = (int)($data['teams_count'] ?? 0);

        if ($name === '' || $teamsCount <= 1) {
            return ['ok' => false, 'error' => 'Competition name and valid teams count are required.'];
        }

        $id = $this->db->insert('competitions', [
            'parent_competition_id' => !empty($data['parent_competition_id']) ? (int)$data['parent_competition_id'] : null,
            'code' => trim((string)($data['code'] ?? '')) ?: null,
            'name' => $name,
            'type' => $type,
            'country' => trim((string)($data['country'] ?? '')) ?: null,
            'level' => max(1, (int)($data['level'] ?? 1)),
            'teams_count' => $teamsCount,
            'promotion_slots' => max(0, (int)($data['promotion_slots'] ?? 0)),
            'relegation_slots' => max(0, (int)($data['relegation_slots'] ?? 0)),
            'is_active' => isset($data['is_active']) ? 1 : 0,
        ]);

        return ['ok' => true, 'competition_id' => $id];
    }

    public function updateCompetition(int $competitionId, array $data): array {
        if ($competitionId <= 0) {
            return ['ok' => false, 'error' => 'Invalid competition.'];
        }

        $affected = $this->db->execute(
            "UPDATE competitions SET
                parent_competition_id = :parent_competition_id,
                code = :code,
                name = :name,
                type = :type,
                country = :country,
                level = :level,
                teams_count = :teams_count,
                promotion_slots = :promotion_slots,
                relegation_slots = :relegation_slots,
                is_active = :is_active
             WHERE id = :id",
            [
                'parent_competition_id' => !empty($data['parent_competition_id']) ? (int)$data['parent_competition_id'] : null,
                'code' => trim((string)($data['code'] ?? '')) ?: null,
                'name' => trim((string)($data['name'] ?? '')),
                'type' => trim((string)($data['type'] ?? 'LEAGUE')),
                'country' => trim((string)($data['country'] ?? '')) ?: null,
                'level' => max(1, (int)($data['level'] ?? 1)),
                'teams_count' => max(2, (int)($data['teams_count'] ?? 2)),
                'promotion_slots' => max(0, (int)($data['promotion_slots'] ?? 0)),
                'relegation_slots' => max(0, (int)($data['relegation_slots'] ?? 0)),
                'is_active' => isset($data['is_active']) ? 1 : 0,
                'id' => $competitionId,
            ]
        );

        return ['ok' => true, 'updated' => $affected];
    }

    public function toggleCompetition(int $competitionId, bool $isActive): void {
        $this->db->execute("UPDATE competitions SET is_active = ? WHERE id = ?", [$isActive ? 1 : 0, $competitionId]);
    }

    public function createSeason(int $competitionId, string $name, string $startDate, string $endDate): array {
        if ($competitionId <= 0 || trim($name) === '' || trim($startDate) === '' || trim($endDate) === '') {
            return ['ok' => false, 'error' => 'Competition, name and date range are required.'];
        }

        $seasonId = $this->db->insert('seasons', [
            'competition_id' => $competitionId,
            'name' => trim($name),
            'start_date' => $startDate,
            'end_date' => $endDate,
            'status' => 'UPCOMING',
            'current_week' => 0
        ]);

        return ['ok' => true, 'season_id' => $seasonId];
    }

    public function startSeason(int $seasonId): array {
        $season = $this->db->fetchOne("SELECT * FROM seasons WHERE id = ?", [$seasonId]);
        if (!$season) return ['ok' => false, 'error' => 'Season not found.'];
        if ($season['status'] === 'ACTIVE') return ['ok' => false, 'error' => 'Season is already active.'];

        $active = $this->db->fetchOne(
            "SELECT id FROM seasons WHERE competition_id = ? AND status = 'ACTIVE' LIMIT 1",
            [(int)$season['competition_id']]
        );
        if ($active) {
            return ['ok' => false, 'error' => 'Another active season already exists for this competition.'];
        }

        $this->db->execute("UPDATE seasons SET status = 'ACTIVE' WHERE id = ?", [$seasonId]);
        return ['ok' => true];
    }

    public function endSeason(int $seasonId): array {
        $season = $this->db->fetchOne("SELECT * FROM seasons WHERE id = ?", [$seasonId]);
        if (!$season) return ['ok' => false, 'error' => 'Season not found.'];
        if ($season['status'] === 'FINISHED') return ['ok' => false, 'error' => 'Season already finished.'];

        $this->db->execute("UPDATE seasons SET status = 'FINISHED' WHERE id = ?", [$seasonId]);
        return ['ok' => true];
    }

    public function addSeasonParticipant(int $seasonId, int $clubId, string $entryType): array {
        $season = $this->db->fetchOne("SELECT id FROM seasons WHERE id = ?", [$seasonId]);
        if (!$season) return ['ok' => false, 'error' => 'Season not found.'];

        $club = $this->db->fetchOne("SELECT id FROM clubs WHERE id = ?", [$clubId]);
        if (!$club) return ['ok' => false, 'error' => 'Club not found.'];

        $normalizedEntry = strtolower(trim($entryType));
        if (!in_array($normalizedEntry, self::ENTRY_TYPES, true)) {
            return ['ok' => false, 'error' => 'Invalid entry type.'];
        }

        $dup = $this->db->fetchOne(
            "SELECT id FROM club_seasons WHERE season_id = ? AND club_id = ?",
            [$seasonId, $clubId]
        );
        if ($dup) {
            return ['ok' => false, 'error' => 'Club is already assigned to this season.'];
        }

        $this->db->insert('club_seasons', [
            'season_id' => $seasonId,
            'club_id' => $clubId,
            'entry_type' => $normalizedEntry,
        ]);

        return ['ok' => true];
    }

    public function removeSeasonParticipant(int $seasonId, int $clubId): array {
        $exists = $this->db->fetchOne(
            "SELECT id FROM club_seasons WHERE season_id = ? AND club_id = ?",
            [$seasonId, $clubId]
        );
        if (!$exists) {
            return ['ok' => false, 'error' => 'Participant assignment not found.'];
        }

        $fixturesCount = (int)($this->db->fetchOne(
            "SELECT COUNT(*) AS c FROM matches WHERE season_id = ?",
            [$seasonId]
        )['c'] ?? 0);

        if ($fixturesCount > 0) {
            return ['ok' => false, 'error' => 'Cannot remove participant after fixtures are generated.'];
        }

        $this->db->execute(
            "DELETE FROM club_seasons WHERE season_id = ? AND club_id = ?",
            [$seasonId, $clubId]
        );

        return ['ok' => true];
    }

    public function getSeasonParticipants(int $seasonId): array {
        return $this->db->fetchAll(
            "SELECT cs.club_id, cs.season_id, cs.entry_type, c.name AS club_name
             FROM club_seasons cs
             JOIN clubs c ON c.id = cs.club_id
             WHERE cs.season_id = ?
             ORDER BY c.name ASC",
            [$seasonId]
        );
    }

    public function generateFixtures(int $seasonId, bool $regenerate = false): array {
        $season = $this->db->fetchOne("SELECT * FROM seasons WHERE id = ?", [$seasonId]);
        if (!$season) return ['ok' => false, 'error' => 'Season not found.'];

        $existingCount = (int)($this->db->fetchOne("SELECT COUNT(*) c FROM matches WHERE season_id = ?", [$seasonId])['c'] ?? 0);
        if ($existingCount > 0 && !$regenerate) {
            return ['ok' => false, 'error' => 'Fixtures already exist for this season.'];
        }

        if ($existingCount > 0 && $regenerate) {
            $unsafe = (int)($this->db->fetchOne(
                "SELECT COUNT(*) c FROM matches WHERE season_id = ? AND status IN ('LIVE','FINISHED')",
                [$seasonId]
            )['c'] ?? 0);
            if ($unsafe > 0) {
                return ['ok' => false, 'error' => 'Cannot regenerate after live/finished matches.'];
            }
            $this->db->execute("DELETE FROM matches WHERE season_id = ?", [$seasonId]);
        }

        $competition = $this->db->fetchOne("SELECT * FROM competitions WHERE id = ?", [(int)$season['competition_id']]);
        if (!$competition || !in_array($competition['type'], ['LEAGUE', 'CHAMPIONS_LEAGUE'], true)) {
            return ['ok' => false, 'error' => 'Fixture generation is only available for league-style competitions in MVP.'];
        }

        $clubIds = $this->resolveSeasonClubIds($seasonId);
        $assignedCount = count($clubIds);
        $expectedCount = max(2, (int)$competition['teams_count']);

        if ($assignedCount === 0) {
            return ['ok' => false, 'error' => 'No explicit participants assigned to this season. Assign clubs before generating fixtures.'];
        }

        if ($assignedCount !== $expectedCount) {
            return ['ok' => false, 'error' => "Participant count mismatch: expected {$expectedCount}, assigned {$assignedCount}. Update season participants first."];
        }

        if ($assignedCount < 2) {
            return ['ok' => false, 'error' => 'Not enough clubs for fixture generation.'];
        }

        $schedule = self::buildRoundRobin($clubIds);
        $startDate = new DateTimeImmutable((string)$season['start_date']);
        $week = 1;
        foreach ($schedule as $round) {
            $kickoff = $startDate->modify('+' . (($week - 1) * 7) . ' days')->setTime(12, 0);
            foreach ($round as [$homeId, $awayId]) {
                $this->db->insert('matches', [
                    'season_id' => $seasonId,
                    'home_club_id' => $homeId,
                    'away_club_id' => $awayId,
                    'week' => $week,
                    'scheduled_at' => $kickoff->format('Y-m-d H:i:s'),
                    'status' => 'SCHEDULED',
                ]);
            }
            $week++;
        }

        return ['ok' => true, 'rounds' => count($schedule), 'matches' => array_sum(array_map('count', $schedule))];
    }

    public function getSeasonRolloverReadiness(int $seasonId): array {
        $season = $this->db->fetchOne("SELECT * FROM seasons WHERE id = ?", [$seasonId]);
        if (!$season) {
            return ['ready' => false, 'reason' => 'season_not_found'];
        }

        $competition = $this->db->fetchOne("SELECT * FROM competitions WHERE id = ?", [(int)$season['competition_id']]);
        if (!$competition || !in_array($competition['type'], ['LEAGUE', 'CHAMPIONS_LEAGUE'], true)) {
            return ['ready' => false, 'reason' => 'non_league_competition'];
        }

        $totalMatches = (int)($this->db->fetchOne("SELECT COUNT(*) AS c FROM matches WHERE season_id = ?", [$seasonId])['c'] ?? 0);
        $finishedMatches = (int)($this->db->fetchOne("SELECT COUNT(*) AS c FROM matches WHERE season_id = ? AND status = 'FINISHED'", [$seasonId])['c'] ?? 0);
        $participants = $this->getSeasonParticipants($seasonId);
        $standingsCount = (int)($this->db->fetchOne("SELECT COUNT(*) AS c FROM standings WHERE season_id = ?", [$seasonId])['c'] ?? 0);
        $log = $this->db->fetchOne("SELECT * FROM season_rollover_logs WHERE season_id = ?", [$seasonId]);
        $alreadyFinalized = $log !== null;

        $ready = $totalMatches > 0
            && $totalMatches === $finishedMatches
            && count($participants) >= 2
            && $standingsCount >= count($participants)
            && !$alreadyFinalized;

        return [
            'ready' => $ready,
            'already_finalized' => $alreadyFinalized,
            'total_matches' => $totalMatches,
            'finished_matches' => $finishedMatches,
            'participants_count' => count($participants),
            'standings_count' => $standingsCount,
            'reason' => $ready ? null : 'season_not_ready_for_finalization',
            'rollover_status' => $log['status'] ?? null,
            'rollover_plan' => !empty($log['rollover_plan_json']) ? json_decode((string)$log['rollover_plan_json'], true) : null,
        ];
    }

    public function finalizeSeason(int $seasonId): array {
        $readiness = $this->getSeasonRolloverReadiness($seasonId);
        if (!($readiness['ready'] ?? false)) {
            return ['ok' => false, 'error' => 'Season is not ready to finalize. Ensure all matches are finished and standings are complete.'];
        }

        $season = $this->db->fetchOne("SELECT * FROM seasons WHERE id = ?", [$seasonId]);
        $standings = $this->getOrderedStandings($seasonId);
        $preview = $this->previewRollover($seasonId);

        $finance = new FinanceService($this->db);
        $this->db->beginTransaction();
        try {
            $this->db->insert('season_rollover_logs', [
                'season_id' => $seasonId,
                'competition_id' => (int)$season['competition_id'],
                'status' => 'FINALIZED',
                'finalized_standings_json' => json_encode($standings, JSON_UNESCAPED_UNICODE),
                'rollover_plan_json' => json_encode($preview, JSON_UNESCAPED_UNICODE),
                'finalized_at' => date('Y-m-d H:i:s'),
            ]);

            $this->db->execute("UPDATE seasons SET status = 'FINISHED' WHERE id = ?", [$seasonId]);
            $this->db->commit();
            return ['ok' => true, 'preview' => $preview];
        } catch (Throwable $e) {
            $this->db->rollBack();
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    public function previewRollover(int $seasonId): array {
        $season = $this->db->fetchOne("SELECT * FROM seasons WHERE id = ?", [$seasonId]);
        if (!$season) {
            return ['ok' => false, 'error' => 'Season not found.'];
        }

        $competition = $this->db->fetchOne("SELECT * FROM competitions WHERE id = ?", [(int)$season['competition_id']]);
        if (!$competition || !in_array($competition['type'], ['LEAGUE', 'CHAMPIONS_LEAGUE'], true)) {
            return ['ok' => false, 'error' => 'Rollover is available only for league-style competitions in MVP.'];
        }

        $standings = $this->getOrderedStandings($seasonId);
        if (empty($standings)) {
            return ['ok' => false, 'error' => 'No standings available for season rollover.'];
        }

        $promotionSlots = max(0, (int)($competition['promotion_slots'] ?? 0));
        $relegationSlots = max(0, (int)($competition['relegation_slots'] ?? 0));

        $promoted = array_slice($standings, 0, $promotionSlots);
        $relegated = $relegationSlots > 0 ? array_slice($standings, -$relegationSlots) : [];

        $promotedIds = array_map(fn($r) => (int)$r['club_id'], $promoted);
        $relegatedIds = array_map(fn($r) => (int)$r['club_id'], $relegated);

        $direct = array_values(array_filter($standings, fn($r) => !in_array((int)$r['club_id'], $promotedIds, true) && !in_array((int)$r['club_id'], $relegatedIds, true)));

        $upperCompetition = !empty($competition['parent_competition_id'])
            ? $this->db->fetchOne("SELECT * FROM competitions WHERE id = ?", [(int)$competition['parent_competition_id']])
            : null;

        $lowerCompetition = $this->db->fetchOne(
            "SELECT * FROM competitions
             WHERE parent_competition_id = ?
             ORDER BY id ASC LIMIT 1",
            [(int)$competition['id']]
        );

        return [
            'ok' => true,
            'season_id' => $seasonId,
            'competition_id' => (int)$competition['id'],
            'promotion_slots' => $promotionSlots,
            'relegation_slots' => $relegationSlots,
            'promoted' => $promoted,
            'relegated' => $relegated,
            'direct' => $direct,
            'upper_competition_id' => (int)($upperCompetition['id'] ?? 0),
            'lower_competition_id' => (int)($lowerCompetition['id'] ?? 0),
        ];
    }

    public function applyRollover(int $seasonId, bool $autoCreateSeasons = true): array {
        $finalized = $this->db->fetchOne("SELECT * FROM season_rollover_logs WHERE season_id = ?", [$seasonId]);
        if (!$finalized) {
            return ['ok' => false, 'error' => 'Season must be finalized before applying rollover.'];
        }
        if (($finalized['status'] ?? '') === 'APPLIED') {
            return ['ok' => false, 'error' => 'Rollover has already been applied for this season.'];
        }

        $plan = $this->previewRollover($seasonId);
        if (!($plan['ok'] ?? false)) {
            return ['ok' => false, 'error' => $plan['error'] ?? 'Unable to prepare rollover plan.'];
        }

        $season = $this->db->fetchOne("SELECT * FROM seasons WHERE id = ?", [$seasonId]);
        $competition = $this->db->fetchOne("SELECT * FROM competitions WHERE id = ?", [(int)$season['competition_id']]);

        $this->db->beginTransaction();
        try {
            $currentNextSeasonId = $this->resolveOrCreateNextSeason((int)$competition['id'], $season, $autoCreateSeasons);
            $this->applyAssignmentsToSeason($currentNextSeasonId, $plan['direct'], 'direct');

            if (!empty($plan['upper_competition_id']) && !empty($plan['promoted'])) {
                $upperSeasonId = $this->resolveOrCreateNextSeason((int)$plan['upper_competition_id'], $season, $autoCreateSeasons);
                $this->applyAssignmentsToSeason($upperSeasonId, $plan['promoted'], 'promoted');
            }

            if (!empty($plan['lower_competition_id']) && !empty($plan['relegated'])) {
                $lowerSeasonId = $this->resolveOrCreateNextSeason((int)$plan['lower_competition_id'], $season, $autoCreateSeasons);
                $this->applyAssignmentsToSeason($lowerSeasonId, $plan['relegated'], 'relegated');
            }

            if (!empty($plan['promoted'])) {
                foreach ($plan['promoted'] as $club) {
                    $finance->postSeasonReward((int)$club['club_id'], $seasonId, 250000, 'Promotion reward');
                }
            }
            if (!empty($plan['direct'])) {
                $champion = $plan['direct'][0] ?? null;
                if ($champion) {
                    $finance->postSeasonReward((int)$champion['club_id'], $seasonId, 500000, 'Title reward');
                }
            }

            $this->db->execute(
                "UPDATE season_rollover_logs SET status = 'APPLIED', applied_at = NOW() WHERE season_id = ?",
                [$seasonId]
            );

            $this->db->commit();
            return ['ok' => true, 'plan' => $plan];
        } catch (Throwable $e) {
            $this->db->rollBack();
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    private function applyAssignmentsToSeason(int $targetSeasonId, array $rows, string $entryType): void {
        $existing = (int)($this->db->fetchOne("SELECT COUNT(*) AS c FROM club_seasons WHERE season_id = ?", [$targetSeasonId])['c'] ?? 0);
        if ($existing > 0) {
            throw new RuntimeException('Target next-season already has manual participant assignments. Rollover apply is blocked for safety.');
        }

        foreach ($rows as $row) {
            $clubId = (int)$row['club_id'];
            $dup = $this->db->fetchOne(
                "SELECT id FROM club_seasons WHERE season_id = ? AND club_id = ?",
                [$targetSeasonId, $clubId]
            );
            if ($dup) {
                continue;
            }
            $this->db->insert('club_seasons', [
                'season_id' => $targetSeasonId,
                'club_id' => $clubId,
                'entry_type' => $entryType,
            ]);
        }
    }

    private function resolveOrCreateNextSeason(int $competitionId, array $sourceSeason, bool $autoCreate): int {
        $existing = $this->db->fetchOne(
            "SELECT id FROM seasons
             WHERE competition_id = ? AND start_date > ?
             ORDER BY start_date ASC LIMIT 1",
            [$competitionId, $sourceSeason['start_date']]
        );
        if ($existing) {
            return (int)$existing['id'];
        }

        if (!$autoCreate) {
            throw new RuntimeException('No next season exists for target competition.');
        }

        $start = (new DateTimeImmutable((string)$sourceSeason['start_date']))->modify('+1 year');
        $end = (new DateTimeImmutable((string)$sourceSeason['end_date']))->modify('+1 year');
        $name = trim((string)$sourceSeason['name']) . ' Next';

        return (int)$this->db->insert('seasons', [
            'competition_id' => $competitionId,
            'name' => $name,
            'start_date' => $start->format('Y-m-d'),
            'end_date' => $end->format('Y-m-d'),
            'status' => 'UPCOMING',
            'current_week' => 0,
        ]);
    }

    private function getOrderedStandings(int $seasonId): array {
        return $this->db->fetchAll(
            "SELECT s.*, c.name AS club_name
             FROM standings s
             JOIN clubs c ON c.id = s.club_id
             WHERE s.season_id = ?
             ORDER BY s.points DESC, s.goal_diff DESC, s.goals_for DESC, s.club_id ASC",
            [$seasonId]
        );
    }

    public function getFixturesBySeason(int $seasonId): array {
        return $this->db->fetchAll(
            "SELECT m.*, hc.name AS home_club_name, ac.name AS away_club_name
             FROM matches m
             JOIN clubs hc ON hc.id = m.home_club_id
             JOIN clubs ac ON ac.id = m.away_club_id
             WHERE m.season_id = ?
             ORDER BY m.week ASC, m.scheduled_at ASC",
            [$seasonId]
        );
    }

    public static function buildRoundRobin(array $clubIds): array {
        $teams = array_values(array_unique($clubIds));
        sort($teams);

        if (count($teams) % 2 === 1) {
            $teams[] = 0; // bye
        }

        $n = count($teams);
        $rounds = [];

        for ($r = 0; $r < $n - 1; $r++) {
            $round = [];
            for ($i = 0; $i < $n / 2; $i++) {
                $home = $teams[$i];
                $away = $teams[$n - 1 - $i];
                if ($home !== 0 && $away !== 0) {
                    $round[] = [$home, $away];
                }
            }
            $rounds[] = $round;
            $pivot = $teams[0];
            $rest = array_slice($teams, 1);
            $last = array_pop($rest);
            array_unshift($rest, $last);
            $teams = array_merge([$pivot], $rest);
        }

        $secondLegs = [];
        foreach ($rounds as $round) {
            $rev = [];
            foreach ($round as [$h, $a]) {
                $rev[] = [$a, $h];
            }
            $secondLegs[] = $rev;
        }

        return array_merge($rounds, $secondLegs);
    }

    private function resolveSeasonClubIds(int $seasonId): array {
        $rows = $this->db->fetchAll(
            "SELECT club_id FROM club_seasons WHERE season_id = ? ORDER BY club_id ASC",
            [$seasonId]
        );
        return array_map(fn($r) => (int)$r['club_id'], $rows);
    }

    private function ensureRolloverTable(): void {
        $this->db->execute(
            "CREATE TABLE IF NOT EXISTS season_rollover_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                season_id INT NOT NULL,
                competition_id INT NOT NULL,
                status ENUM('FINALIZED','APPLIED') DEFAULT 'FINALIZED',
                finalized_standings_json JSON,
                rollover_plan_json JSON,
                finalized_at DATETIME NOT NULL,
                applied_at DATETIME,
                UNIQUE KEY uniq_season_rollover (season_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }
}
