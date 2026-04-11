<?php

class LeagueSeeder
{
    public function __construct(private PDO $db) {}

    public function run(): void
    {
        $this->db->exec("DELETE FROM standings");
        $this->db->exec("DELETE FROM club_seasons");
        $this->db->exec("DELETE FROM seasons");
        $this->db->exec("DELETE FROM competitions");

        $competitions = [
            [1, 'لیگ برتر ایران', 'LEAGUE', 'Iran', 1, 18],
            [2, 'جام حذفی', 'CUP', 'Iran', 1, 32],
            [3, 'سوپر جام', 'FRIENDLY', 'Iran', 1, 2],
        ];

        $stmt = $this->db->prepare(
            "INSERT INTO competitions (id, name, type, country, level, teams_count) VALUES (?, ?, ?, ?, ?, ?)"
        );
        foreach ($competitions as $c) {
            $stmt->execute($c);
        }

        $this->db->exec(
            "INSERT INTO seasons (id, competition_id, name, start_date, end_date, status, current_week)
             VALUES (1, 1, '2025/26', '2025-08-01', '2026-05-31', 'ACTIVE', 1)"
        );

        echo "  Competitions and active season seeded.\n";
    }
}
