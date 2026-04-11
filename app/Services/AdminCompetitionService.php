<?php
// app/Services/AdminCompetitionService.php

class AdminCompetitionService {
    private Database $db;

    public function __construct(?Database $db = null) {
        $this->db = $db ?? Database::getInstance();
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
        }

        return $competitions;
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

        $clubIds = $this->resolveSeasonClubIds($seasonId, (int)$competition['teams_count']);
        if (count($clubIds) < 2) {
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

    private function resolveSeasonClubIds(int $seasonId, int $targetCount): array {
        $rows = $this->db->fetchAll("SELECT club_id FROM club_seasons WHERE season_id = ? ORDER BY club_id ASC", [$seasonId]);
        $clubIds = array_map(fn($r) => (int)$r['club_id'], $rows);

        if (empty($clubIds)) {
            $fallback = $this->db->fetchAll("SELECT id FROM clubs ORDER BY id ASC LIMIT ?", [$targetCount]);
            $clubIds = array_map(fn($r) => (int)$r['id'], $fallback);
            foreach ($clubIds as $clubId) {
                $this->db->insert('club_seasons', ['club_id' => $clubId, 'season_id' => $seasonId]);
            }
        }

        return $clubIds;
    }
}
