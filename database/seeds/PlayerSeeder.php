<?php

class PlayerSeeder
{
    public function __construct(private PDO $db) {}

    private array $firstNames = ['علی','محمد','حسین','رضا','امیر','مهدی','سعید','کریم','جواد','فرهاد','مجید','شاهین','بهزاد','نیما','آرش'];
    private array $lastNames = ['احمدی','محمدی','حسینی','رضایی','کریمی','موسوی','جعفری','نوری','صادقی','رحیمی','قاسمی','مرادی','علوی','طاهری','شریفی'];
    private array $positions = ['GK','LB','RB','CB','CDM','CM','CAM','LW','RW','ST','CF'];

    public function run(): void
    {
        $this->db->exec("DELETE FROM player_season_stats");
        $this->db->exec("DELETE FROM player_abilities");
        $this->db->exec("DELETE FROM players");

        $playerStmt = $this->db->prepare(
            "INSERT INTO players
                (club_id, first_name, last_name, nationality, birth_date, position, preferred_foot,
                 pace, shooting, passing, dribbling, defending, physical,
                 overall, potential, form, fatigue, morale, wage, contract_end, market_value)
             VALUES
                (?, ?, ?, 'Iran', ?, ?, 'RIGHT', ?, ?, ?, ?, ?, ?, ?, ?, 6.5, 10, 7.0, ?, ?, ?)"
        );

        $statStmt = $this->db->prepare(
            "INSERT INTO player_season_stats
                (player_id, season_id, club_id, appearances, goals, assists, yellow_cards, red_cards, avg_rating)
             VALUES (?, 1, ?, 0, 0, 0, 0, 0, 0.0)"
        );

        for ($clubId = 1; $clubId <= 18; $clubId++) {
            for ($i = 0; $i < 25; $i++) {
                $pos = $this->positions[array_rand($this->positions)];
                $age = rand(18, 34);
                $birthDate = date('Y-m-d', strtotime('-' . $age . ' years -' . rand(0, 364) . ' days'));

                $pace = rand(45, 85);
                $shoot = rand(40, 85);
                $pass = rand(40, 85);
                $dribble = rand(40, 85);
                $def = rand(35, 85);
                $phys = rand(45, 85);
                $overall = (int)round(($pace + $shoot + $pass + $dribble + $def + $phys) / 6);
                $potential = min(99, $overall + rand(1, 12));
                $marketValue = $overall * $overall * 1200;
                $wage = (int)($marketValue * 0.001);
                $contractEnd = date('Y-m-d', strtotime('+' . rand(1, 5) . ' years'));

                $playerStmt->execute([
                    $clubId,
                    $this->firstNames[array_rand($this->firstNames)],
                    $this->lastNames[array_rand($this->lastNames)],
                    $birthDate,
                    $pos,
                    $pace, $shoot, $pass, $dribble, $def, $phys,
                    $overall, $potential,
                    $wage,
                    $contractEnd,
                    $marketValue,
                ]);

                $playerId = (int)$this->db->lastInsertId();
                $statStmt->execute([$playerId, $clubId]);
            }
        }

        echo "  450 players seeded across 18 clubs.\n";
    }
}
