<?php
// app/Services/AbilityService.php

class AbilityService {
    private Database $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function getPlayerAbilities(int $playerId): array {
        return $this->db->fetchAll(
            "SELECT a.*, pa.unlocked_at, pa.is_active
             FROM player_abilities pa
             JOIN abilities a ON pa.ability_id = a.id
             WHERE pa.player_id = ?
             ORDER BY pa.unlocked_at DESC",
            [$playerId]
        );
    }

    public function checkAndUnlock(int $playerId): array {
        $unlocked = [];

        $stats = $this->db->fetchOne(
            "SELECT
                SUM(pss.goals) AS career_goals,
                SUM(pss.assists) AS career_assists,
                SUM(pss.appearances) AS career_apps,
                AVG(pss.avg_rating) AS career_rating
             FROM player_season_stats pss
             WHERE pss.player_id = ?",
            [$playerId]
        ) ?? [];

        $player = $this->db->fetchOne("SELECT * FROM players WHERE id = ?", [$playerId]);
        if (!$player) {
            return [];
        }

        $age = (int)date('Y') - (int)date('Y', strtotime((string)$player['birth_date']));

        $rules = [
            'POACHER' => ((int)($stats['career_goals'] ?? 0)) >= 80,
            'VETERAN' => ((int)($stats['career_apps'] ?? 0)) >= 200 || $age >= 33,
            'CAPTAIN_LEADER' => ((int)($stats['career_apps'] ?? 0)) >= 150 && ((float)($stats['career_rating'] ?? 0)) >= 7.5,
            'SUPER_SUB' => false,
        ];

        foreach ($rules as $code => $condition) {
            if (!$condition) continue;

            $ability = $this->db->fetchOne("SELECT id FROM abilities WHERE code = ?", [$code]);
            if (!$ability) continue;

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
                $unlocked[] = $code;
            }
        }

        return $unlocked;
    }

    public function seedAbilities(): void {
        $abilities = [
            ['code' => 'CLINICAL_FINISHER', 'name' => 'Clinical Finisher', 'description' => '+15% finishing in the box', 'type' => 'INNATE', 'category' => 'ATTACKING'],
            ['code' => 'LIGHTNING_PACE', 'name' => 'Lightning Pace', 'description' => '+10% sprint speed in transitions', 'type' => 'INNATE', 'category' => 'PHYSICAL'],
            ['code' => 'AERIAL_THREAT', 'name' => 'Aerial Threat', 'description' => '+20% aerial duel chance on crosses', 'type' => 'INNATE', 'category' => 'ATTACKING'],
            ['code' => 'IRON_WALL', 'name' => 'Iron Wall', 'description' => '+12% defensive resilience late game', 'type' => 'INNATE', 'category' => 'DEFENDING'],
            ['code' => 'PLAYMAKER', 'name' => 'Playmaker', 'description' => '+15% key-pass quality', 'type' => 'INNATE', 'category' => 'MENTAL'],
            ['code' => 'POACHER', 'name' => 'Poacher', 'description' => '+10% chance to score after 75th minute', 'type' => 'ACQUIRED', 'category' => 'ATTACKING'],
            ['code' => 'VETERAN', 'name' => 'Veteran', 'description' => '+5% all-around consistency', 'type' => 'ACQUIRED', 'category' => 'MENTAL'],
            ['code' => 'CAPTAIN_LEADER', 'name' => 'Captain & Leader', 'description' => '+8% team morale effect', 'type' => 'ACQUIRED', 'category' => 'LEADERSHIP'],
        ];

        foreach ($abilities as $ability) {
            $exists = $this->db->fetchOne("SELECT id FROM abilities WHERE code = ?", [$ability['code']]);
            if (!$exists) {
                $this->db->insert('abilities', $ability);
            }
        }
    }
}
