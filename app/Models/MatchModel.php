<?php
// app/Models/MatchModel.php

class MatchModel extends BaseModel {
    protected string $table = 'matches';

    public function getScheduled(): array {
        return $this->db->fetchAll(
            "SELECT m.*, hc.name AS home_club_name, ac.name AS away_club_name, comp.name AS competition_name
             FROM matches m
             JOIN clubs hc ON m.home_club_id = hc.id
             JOIN clubs ac ON m.away_club_id = ac.id
             JOIN seasons s ON m.season_id = s.id
             JOIN competitions comp ON s.competition_id = comp.id
             WHERE m.status = 'SCHEDULED' AND m.scheduled_at <= NOW()
             ORDER BY m.scheduled_at ASC"
        );
    }

    public function getByClub(int $clubId, int $limit = 10): array {
        return $this->db->fetchAll(
            "SELECT m.*, hc.name AS home_club_name, ac.name AS away_club_name,
                    CASE WHEN m.home_club_id = ? THEN ac.name ELSE hc.name END AS opponent_name,
                    CASE WHEN m.home_club_id = ? THEN 'HOME' ELSE 'AWAY' END AS home_away,
                    DATE_FORMAT(m.scheduled_at, '%Y-%m-%d %H:%i') AS match_date,
                    m.home_score AS home_goals,
                    m.away_score AS away_goals
             FROM matches m
             JOIN clubs hc ON m.home_club_id = hc.id
             JOIN clubs ac ON m.away_club_id = ac.id
             WHERE (m.home_club_id = ? OR m.away_club_id = ?)
               AND m.status = 'FINISHED'
             ORDER BY m.scheduled_at DESC
             LIMIT ?",
            [$clubId, $clubId, $clubId, $clubId, $limit]
        );
    }

    public function getUpcoming(int $clubId, int $limit = 5): array {
        return $this->db->fetchAll(
            "SELECT m.*, hc.name AS home_club_name, ac.name AS away_club_name, comp.name AS competition_name,
                    CASE WHEN m.home_club_id = ? THEN ac.name ELSE hc.name END AS opponent_name,
                    CASE WHEN m.home_club_id = ? THEN 'HOME' ELSE 'AWAY' END AS home_away,
                    DATE_FORMAT(m.scheduled_at, '%Y-%m-%d %H:%i') AS match_date
             FROM matches m
             JOIN clubs hc ON m.home_club_id = hc.id
             JOIN clubs ac ON m.away_club_id = ac.id
             JOIN seasons s ON m.season_id = s.id
             JOIN competitions comp ON s.competition_id = comp.id
             WHERE (m.home_club_id = ? OR m.away_club_id = ?)
               AND m.status = 'SCHEDULED'
             ORDER BY m.scheduled_at ASC
             LIMIT ?",
            [$clubId, $clubId, $clubId, $clubId, $limit]
        );
    }

    public function getWithEvents(int $matchId): ?array {
        $match = $this->find($matchId);
        if (!$match) return null;

        $match['events'] = $this->db->fetchAll(
            "SELECT me.*, CONCAT(p.first_name, ' ', p.last_name) AS player_name,
                    CONCAT(ap.first_name, ' ', ap.last_name) AS secondary_player_name
             FROM match_events me
             LEFT JOIN players p ON me.player_id = p.id
             LEFT JOIN players ap ON me.assist_player_id = ap.id
             WHERE me.match_id = ?
             ORDER BY me.minute ASC",
            [$matchId]
        );

        $match['ratings'] = $this->db->fetchAll(
            "SELECT pmr.*, CONCAT(p.first_name, ' ', p.last_name) AS player_name, p.position
             FROM player_match_ratings pmr
             JOIN players p ON pmr.player_id = p.id
             WHERE pmr.match_id = ?
             ORDER BY pmr.rating DESC",
            [$matchId]
        );

        return $match;
    }

    public function scheduleMatch(int $seasonId, int $homeId, int $awayId, int $week, string $scheduledAt): int {
        return $this->create([
            'season_id' => $seasonId,
            'home_club_id' => $homeId,
            'away_club_id' => $awayId,
            'week' => $week,
            'scheduled_at' => $scheduledAt,
            'status' => 'SCHEDULED'
        ]);
    }

    public function getHeadToHead(int $clubA, int $clubB, int $limit = 10): array {
        return $this->db->fetchAll(
            "SELECT * FROM matches
             WHERE ((home_club_id = ? AND away_club_id = ?) OR (home_club_id = ? AND away_club_id = ?))
               AND status = 'FINISHED'
             ORDER BY scheduled_at DESC
             LIMIT ?",
            [$clubA, $clubB, $clubB, $clubA, $limit]
        );
    }
}
