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

        // دریافت آمار کل بازیکن
        $stats = $this->db->fetchOne(
            "SELECT 
                SUM(pss.goals) as career_goals,
                SUM(pss.assists) as career_assists,
                SUM(pss.appearances) as career_apps,
                AVG(pss.average_rating) as career_rating
             FROM player_season_stats pss
             WHERE pss.player_id = ?",
            [$playerId]
        );

        $player = $this->db->fetchOne("SELECT * FROM players WHERE id = ?", [$playerId]);
        $age = (int)date('Y') - (int)date('Y', strtotime($player['date_of_birth']));

        // قوانین باز شدن
        $rules = [
            'POACHER' => $stats['career_goals'] >= 80,
            'VETERAN' => $stats['career_apps'] >= 200,
            'CAPTAIN_LEADER' => $stats['career_apps'] >= 150 && $stats['career_rating'] >= 7.5,
            'SUPER_SUB' => false, // نیاز به منطق پیچیده‌تر (گل از روی نیمکت)
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
            ['code' => 'CLINICAL_FINISHER', 'name' => 'Clinical Finisher', 'description' => '+15% دقت شوت در محوطه جریمه', 'type' => 'INNATE', 'rarity' => 'RARE'],
            ['code' => 'LIGHTNING_PACE', 'name' => 'Lightning Pace', 'description' => '+10% سرعت در counter attack', 'type' => 'INNATE', 'rarity' => 'RARE'],
            ['code' => 'AERIAL_THREAT', 'name' => 'Aerial Threat', 'description' => '+20% شانس گل از ضربه سر', 'type' => 'INNATE', 'rarity' => 'UNCOMMON'],
            ['code' => 'IRON_WALL', 'name' => 'Iron Wall', 'description' => '+12% دفاع در دقایق پایانی', 'type' => 'INNATE', 'rarity' => 'RARE'],
            ['code' => 'PLAYMAKER', 'name' => 'Playmaker', 'description' => '+15% دقت پاس کلیدی', 'type' => 'INNATE', 'rarity' => 'EPIC'],
            ['code' => 'POACHER', 'name' => 'Poacher', 'description' => '+10% شانس گل در دقیقه 75+', 'type' => 'ACQUIRED', 'rarity' => 'EPIC'],
            ['code' => 'VETERAN', 'name' => 'Veteran', 'description' => '+5% به تمام ویژگی‌ها', 'type' => 'ACQUIRED', 'rarity' => 'LEGENDARY'],
            ['code' => 'CAPTAIN_LEADER', 'name' => 'Captain & Leader', 'description' => '+8% روحیه تیم', 'type' => 'ACQUIRED', 'rarity' => 'EPIC'],
        ];

        foreach ($abilities as $ability) {
            $exists = $this->db->fetchOne("SELECT id FROM abilities WHERE code = ?", [$ability['code']]);
            if (!$exists) {
                $this->db->insert('abilities', $ability);
            }
        }
    }
}
