<?php
// app/Models/MatchModel.php

class MatchModel extends BaseModel {
    protected string $table = 'matches';

    public function getScheduled(): array {
        return $this->db->fetchAll(
            "SELECT m.*,
                    hc.name AS home_club_name,
                    ac.name AS away_club_name,
                    comp.name AS competition_name
             FROM matches m
             JOIN clubs hc ON m.home_club_id = hc.id
             JOIN clubs ac ON m.away_club_id = ac.id
             JOIN competitions comp ON m.competition_id = comp.id
             WHERE m.status = 'SCHEDULED'
               AND m.match_time <= NOW()
             ORDER BY m.match_time ASC"
        );
    }

    public function getByClub(int $clubId, int $limit = 10): array {
        return $this->db->fetchAll(
            "SELECT m.*,
                    hc.name AS home_club_name,
                    ac.name AS away_club_name
             FROM matches m
             JOIN clubs hc ON m.home_club_id = hc.id
             JOIN clubs ac ON m.away_club_id = ac.id
             WHERE (m.home_club_id = ? OR m.away_club_id = ?)
               AND m.status = 'FINISHED'
             ORDER BY m.match_time DESC
             LIMIT ?",
            [$clubId, $clubId, $limit]
        );
    }

    public function getUpcoming(int $clubId, int $limit = 5): array {
        return $this->db->fetchAll(
            "SELECT m.*,
                    hc.name AS home_club_name,
                    ac.name AS away_club_name,
                    comp.name AS competition_name
             FROM matches m
             JOIN clubs hc ON m.home_club_id = hc.id
             JOIN clubs ac ON m.away_club_id = ac.id
             JOIN competitions comp ON m.competition_id = comp.id
             WHERE (m.home_club_id = ? OR m.away_club_id = ?)
               AND m.status = 'SCHEDULED'
             ORDER BY m.match_time ASC
             LIMIT ?",
            [$clubId, $clubId, $limit]
        );
    }

    public function getWithEvents(int $matchId): ?array {
        $match = $this->find($matchId);
        if (!$match) return null;

        $match['events'] = $this->db->fetchAll(
            "SELECT me.*, p.name AS player_name
             FROM match_events me
             LEFT JOIN players p ON me.player_id = p.id
             WHERE me.match_id = ?
             ORDER BY me.minute ASC",
            [$matchId]
        );

        $match['ratings'] = $this->db->fetchAll(
            "SELECT pmr.*, p.name AS player_name, p.position
             FROM player_match_ratings pmr
             JOIN players p ON pmr.player_id = p.id
             WHERE pmr.match_id = ?
             ORDER BY pmr.rating DESC",
            [$matchId]
        );

        return $match;
    }

    public function scheduleMatch(int $homeId, int $awayId, int $competitionId, string $matchTime): int {
        return $this->create([
            'home_club_id'   => $homeId,
            'away_club_id'   => $awayId,
            'competition_id' => $competitionId,
            'match_time'     => $matchTime,
            'status'         => 'SCHEDULED'
        ]);
    }

    public function getHeadToHead(int $clubA, int $clubB, int $limit = 10): array {
        return $this->db->fetchAll(
            "SELECT * FROM matches
             WHERE ((home_club_id = ? AND away_club_id = ?)
                OR  (home_club_id = ? AND away_club_id = ?))
               AND status = 'FINISHED'
             ORDER BY match_time DESC
             LIMIT ?",
            [$clubA, $clubB, $clubB, $clubA, $limit]
        );
    }
}
