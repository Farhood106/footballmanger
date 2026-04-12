<?php
// app/Models/PlayerModel.php

class PlayerModel extends BaseModel {
    protected string $table = 'players';

    private array $positionWeights = [
        'GK'  => ['pace'=>5, 'shooting'=>2, 'passing'=>10, 'dribbling'=>5, 'defending'=>30, 'physical'=>20, 'goalkeeping'=>28],
        'CB'  => ['pace'=>10,'shooting'=>5, 'passing'=>10, 'dribbling'=>5, 'defending'=>40, 'physical'=>20, 'goalkeeping'=>10],
        'LB'  => ['pace'=>20,'shooting'=>5, 'passing'=>15, 'dribbling'=>15,'defending'=>30, 'physical'=>15, 'goalkeeping'=>0],
        'RB'  => ['pace'=>20,'shooting'=>5, 'passing'=>15, 'dribbling'=>15,'defending'=>30, 'physical'=>15, 'goalkeeping'=>0],
        'CDM' => ['pace'=>10,'shooting'=>8, 'passing'=>20, 'dribbling'=>12,'defending'=>30, 'physical'=>20, 'goalkeeping'=>0],
        'CM'  => ['pace'=>10,'shooting'=>12,'passing'=>25, 'dribbling'=>18,'defending'=>20, 'physical'=>15, 'goalkeeping'=>0],
        'CAM' => ['pace'=>12,'shooting'=>18,'passing'=>25, 'dribbling'=>25,'defending'=>10, 'physical'=>10, 'goalkeeping'=>0],
        'LW'  => ['pace'=>25,'shooting'=>18,'passing'=>15, 'dribbling'=>25,'defending'=>8,  'physical'=>9,  'goalkeeping'=>0],
        'RW'  => ['pace'=>25,'shooting'=>18,'passing'=>15, 'dribbling'=>25,'defending'=>8,  'physical'=>9,  'goalkeeping'=>0],
        'ST'  => ['pace'=>18,'shooting'=>30,'passing'=>12, 'dribbling'=>18,'defending'=>5,  'physical'=>17, 'goalkeeping'=>0],
        'CF'  => ['pace'=>15,'shooting'=>25,'passing'=>18, 'dribbling'=>22,'defending'=>5,  'physical'=>15, 'goalkeeping'=>0],
    ];

    public function getWithAbilities(int $playerId): ?array {
        $player = $this->find($playerId);
        if (!$player) return null;

        $player['full_name'] = $this->buildFullName($player);
        $player['abilities'] = $this->db->fetchAll(
            "SELECT a.* FROM player_abilities pa
             JOIN abilities a ON pa.ability_id = a.id
             WHERE pa.player_id = ? AND pa.is_active = 1",
            [$playerId]
        );

        return $player;
    }

    public function calculateOverall(array $player): int {
        $pos = $player['position'] ?? 'CM';
        $weights = $this->positionWeights[$pos] ?? $this->positionWeights['CM'];

        $score = 0;
        foreach ($weights as $attr => $weight) {
            $score += ($player[$attr] ?? 50) * ($weight / 100);
        }

        return (int)round(min(99, max(40, $score)));
    }

    public function getAvailableForTransfer(int $excludeClubId = 0): array {
        return $this->db->fetchAll(
            "SELECT p.*, CONCAT(p.first_name, ' ', p.last_name) AS full_name, c.name AS club_name
             FROM players p
             LEFT JOIN clubs c ON p.club_id = c.id
             WHERE p.club_id != ?
               AND p.is_retired = 0
               AND p.contract_end IS NOT NULL
             ORDER BY p.overall DESC",
            [$excludeClubId]
        );
    }

    public function updateCondition(int $playerId, array $data): bool {
        $allowed = ['form', 'fatigue', 'morale', 'fitness', 'morale_score', 'is_injured', 'injury_days'];
        $update = array_intersect_key($data, array_flip($allowed));
        return $this->update($playerId, $update);
    }

    public function recoverFatigue(int $clubId, int $amount = 10): void {
        $this->db->query(
            "UPDATE players
             SET fatigue = GREATEST(0, fatigue - ?),
                 fitness = LEAST(100, fitness + ?)
             WHERE club_id = ? AND is_retired = 0",
            [$amount, (int)max(1, floor($amount / 2)), $clubId]
        );
    }

    public function getInjured(int $clubId): array {
        return $this->db->fetchAll(
            "SELECT p.*, i.type AS injury_type, i.severity, i.recovered_at
             FROM players p
             JOIN injuries i ON p.id = i.player_id
             WHERE p.club_id = ? AND p.is_injured = 1 AND i.recovered_at IS NULL",
            [$clubId]
        );
    }

    public function getSeasonStats(int $playerId, int $seasonId): ?array {
        return $this->db->fetchOne(
            "SELECT * FROM player_season_stats
             WHERE player_id = ? AND season_id = ?",
            [$playerId, $seasonId]
        );
    }

    public function getCareerStats(int $playerId): array {
        return $this->db->fetchOne(
            "SELECT
                SUM(goals) AS career_goals,
                SUM(assists) AS career_assists,
                SUM(appearances) AS career_apps,
                SUM(minutes_played) AS career_minutes,
                AVG(avg_rating) AS career_rating
             FROM player_career_history
             WHERE player_id = ?",
            [$playerId]
        ) ?? [];
    }

    private function buildFullName(array $player): string {
        return trim(($player['first_name'] ?? '') . ' ' . ($player['last_name'] ?? ''));
    }
}
