<?php
// app/Models/CompetitionModel.php

class CompetitionModel extends BaseModel {
    protected string $table = 'competitions';

    public function getActive(): array {
        return $this->db->fetchAll(
            "SELECT c.*
             FROM competitions c
             WHERE EXISTS (
                 SELECT 1 FROM seasons s
                 WHERE s.competition_id = c.id AND s.status = 'ACTIVE'
             )
             ORDER BY c.type, c.level, c.name"
        );
    }

    public function getStandings(int $competitionId, int $seasonId): array {
        return $this->db->fetchAll(
            "SELECT s.*, c.name AS club_name, c.badge_url
             FROM standings s
             JOIN clubs c ON s.club_id = c.id
             WHERE s.season_id = ?
               AND EXISTS (
                    SELECT 1 FROM seasons sn
                    WHERE sn.id = s.season_id AND sn.competition_id = ?
               )
             ORDER BY s.points DESC, s.goal_diff DESC, s.goals_for DESC",
            [$seasonId, $competitionId]
        );
    }

    public function getFixtures(int $competitionId, int $seasonId): array {
        return $this->db->fetchAll(
            "SELECT m.*, hc.name AS home_club_name, ac.name AS away_club_name
             FROM matches m
             JOIN clubs hc ON m.home_club_id = hc.id
             JOIN clubs ac ON m.away_club_id = ac.id
             WHERE m.season_id = ?
               AND EXISTS (
                    SELECT 1 FROM seasons sn
                    WHERE sn.id = m.season_id AND sn.competition_id = ?
               )
             ORDER BY m.scheduled_at ASC",
            [$seasonId, $competitionId]
        );
    }

    public function getTopScorers(int $competitionId, int $seasonId, int $limit = 10): array {
        return $this->db->fetchAll(
            "SELECT CONCAT(p.first_name, ' ', p.last_name) AS player_name,
                    p.position,
                    c.name AS club_name,
                    SUM(pss.goals) AS goals,
                    SUM(pss.assists) AS assists
             FROM player_season_stats pss
             JOIN players p ON pss.player_id = p.id
             JOIN clubs c ON pss.club_id = c.id
             WHERE pss.season_id = ?
               AND EXISTS (
                    SELECT 1 FROM seasons sn
                    WHERE sn.id = pss.season_id AND sn.competition_id = ?
               )
             GROUP BY p.id, p.first_name, p.last_name, p.position, c.name
             ORDER BY goals DESC, assists DESC
             LIMIT ?",
            [$seasonId, $competitionId, $limit]
        );
    }
}
