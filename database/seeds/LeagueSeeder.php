<?php

class LeagueSeeder
{
    public function __construct(private PDO $db) {}

    public function run(): void
    {
        // Clear existing
        $this->db->exec("DELETE FROM competitions");
        $this->db->exec("DELETE FROM seasons");

        // Insert current season
        $this->db->exec("
            INSERT INTO seasons (id, name, start_date, end_date, is_active)
            VALUES (1, '1404-1405', '2025-08-01', '2026-05-31', 1)
        ");

        // Insert competitions
        $competitions = [
            [1, 'لیگ برتر ایران',    'LEAGUE', 1, 18, 1],
            [2, 'جام حذفی',          'CUP',    1, 32, 1],
            [3, 'سوپر جام',          'SUPER',  1,  2, 0],
        ];

        $stmt = $this->db->prepare("
            INSERT INTO competitions (id, name, type, season_id, teams_count, is_active)
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        foreach ($competitions as $c) {
            $stmt->execute($c);
        }

        echo "  Leagues and season seeded.\n";
    }
}
