<?php
// app/Models/CompetitionModel.php

class CompetitionModel extends BaseModel {
    protected string $table = 'competitions';

    public function getActive(): array {
        return $this->db->fetchAll(
            "SELECT c.*, l.name AS league_name
             FROM competitions c
             LEFT JOIN leagues l ON c.league_id = l.id
             WHERE c.is_active = 1
             ORDER BY c.type, c.name"
        );
    }

    public function getStandings(int $competitionId, int $seasonId): array {
        return $this->db->fetchAll(
            "SELECT s.*, c.name AS club_name, c.badge_url,
                    (s.goals_for - s.goals_against) AS goal_diff
             FROM standings s
             JOIN clubs c ON s.club_id = c.id
             WHERE s.competition_id = ? AND s.season_id = ?
             ORDER BY s.points DESC, goal_diff DESC, s.goals_for DESC",
            [$competitionId, $seasonId]
        );
    }

    public function getFixtures(int $competitionId, int $seasonId): array {
        return $this->db->fetchAll(
            "SELECT m.*,
                    hc.name AS home_club_name,
                    ac.name AS away_club_name
             FROM matches m
             JOIN clubs hc ON m.home_club_id = hc.id
             JOIN clubs ac ON m.away_club_id = ac.id
             WHERE m.competition_id = ? AND m.season_id = ?
             ORDER BY m.match_time ASC",
            [$competitionId, $seasonId]
        );
    }

    public function getTopScorers(int $competitionId, int $seasonId, int $limit = 10): array {
        return $this->db->fetchAll(
            "SELECT p.name, p.position, c.name AS club_name,
                    SUM(pss.goals) AS goals,
                    SUM(pss.assists) AS assists
             FROM player_season_stats pss
             JOIN players p ON pss.player_id = p.id
             JOIN clubs c ON p.club_id = c.id
             WHERE pss.season_id = ? AND pss.competition_id = ?
             GROUP BY p.id
             ORDER BY goals DESC
             LIMIT ?",
            [$seasonId, $competitionId, $limit]
        );
    }
}
