<?php

class PlayerSeeder
{
    public function __construct(private PDO $db) {}

    // Position pools per formation slot
    private array $positionMap = [
        'GK'  => ['GK'],
        'DEF' => ['CB','CB','LB','RB'],
        'MID' => ['CM','CM','CDM','CAM','LM','RM'],
        'FWD' => ['ST','ST','LW','RW','CF'],
    ];

    // Iranian first names
    private array $firstNames = [
        'علی','محمد','حسین','رضا','امیر','مهدی','سعید','کریم',
        'جواد','فرهاد','مجید','شاهین','بهزاد','نیما','آرش',
        'پویا','سینا','داریوش','کامران','بهرام','وحید','صادق',
        'ابراهیم','یاسر','میلاد','سجاد','حمید','اکبر','ناصر','فریدون',
    ];

    // Iranian last names
    private array $lastNames = [
        'احمدی','محمدی','حسینی','رضایی','کریمی','موسوی','جعفری',
        'نوری','صادقی','رحیمی','قاسمی','مرادی','علوی','طاهری',
        'شریفی','نجفی','حیدری','ابراهیمی','سلیمانی','غلامی',
        'اکبری','یوسفی','باقری','زارعی','منصوری','فرهادی','نادری',
        'صالحی','خانی','پورمحمد',
    ];
    public function run(): void
    {
        $this->db->exec("DELETE FROM player_season_stats");
        $this->db->exec("DELETE FROM player_abilities");
        $this->db->exec("DELETE FROM players");

        $playerId = 1;

        // 25 players per club (18 clubs = 450 players total)
        for ($clubId = 1; $clubId <= 18; $clubId++) {
            $reputation = $this->getClubReputation($clubId);
            $this->seedClubPlayers($clubId, $reputation, $playerId);
            $playerId += 25;
        }

        echo "  " . (18 * 25) . " players seeded across 18 clubs.\n";
    }

    private function seedClubPlayers(int $clubId, int $reputation, int $startId): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO players
                (id, club_id, name, position, age, nationality,
                 pace, shooting, passing, dribbling, defending, physical,
                 overall, potential, market_value, wage,
                 form, morale, fatigue, is_injured)
            VALUES
                (?, ?, ?, ?, ?, 'ایرانی',
                 ?, ?, ?, ?, ?, ?,
                 ?, ?, ?, ?,
                 70, 75, 20, 0)
        ");

        $statStmt = $this->db->prepare("
            INSERT INTO player_season_stats
                (player_id, season_id, competition_id,
                 appearances, goals, assists, yellow_cards, red_cards, avg_rating)
            VALUES (?, 1, 1, 0, 0, 0, 0, 0, 0.00)
        ");

        // Slot distribution: 1 GK, 6 DEF, 8 MID, 6 FWD, 4 utility
        $slots = array_merge(
            array_fill(0, 2, 'GK'),
            array_fill(0, 7, 'DEF'),
            array_fill(0, 9, 'MID'),
            array_fill(0, 7, 'FWD')
        );

        foreach ($slots as $i => $group) {
            $id       = $startId + $i;
            $position = $this->pickPosition($group);
            $age      = rand(18, 34);
            $base     = $reputation - rand(0, 15); // weaker clubs have lower base

            [$pace, $shoot, $pass, $drib, $def, $phys] =
                $this->generateAttributes($position, $base);

            $overall   = $this->calcOverall($position, $pace, $shoot, $pass, $drib, $def, $phys);
            $potential = min(99, $overall + rand(0, max(0, 30 - ($age - 18))));
            $value     = $this->calcValue($overall, $age);
            $wage      = (int)($value * 0.0008);

            $stmt->execute([
                $id, $clubId,
                $this->randomName(),
                $position, $age,
                $pace, $shoot, $pass, $drib, $def, $phys,
                $overall, $potential, $value, $wage,
            ]);

            $statStmt->execute([$id]);
        }
    }

    private function pickPosition(string $group): string
    {
        $map = [
            'GK'  => ['GK'],
            'DEF' => ['CB','CB','LB','RB'],
            'MID' => ['CM','CM','CDM','CAM','LM','RM'],
            'FWD' => ['ST','ST','LW','RW','CF'],
        ];
        $pool = $map[$group];
        return $pool[array_rand($pool)];
    }

    private function generateAttributes(string $pos, int $base): array
    {
        // base clamped between 45-90
        $b = max(45, min(90, $base));

        $v = fn(int $boost) => min(99, max(30, $b + $boost + rand(-8, 8)));

        return match(true) {
            $pos === 'GK'                    => [$v(-10), $v(-20), $v(-5),  $v(-15), $v(+15), $v(+5)],
            in_array($pos, ['CB','LB','RB']) => [$v(0),   $v(-15), $v(-5),  $v(-10), $v(+15), $v(+10)],
            in_array($pos, ['CDM','CM'])     => [$v(0),   $v(-5),  $v(+10), $v(0),   $v(+5),  $v(+5)],
            in_array($pos, ['CAM','LM','RM'])=> [$v(+5),  $v(+5),  $v(+10), $v(+10), $v(-10), $v(0)],
            default                          => [$v(+10), $v(+15), $v(0),   $v(+10), $v(-15), $v(+5)],
        };
    }

    private function calcOverall(string $pos, int $pac, int $sho, int $pas, int $dri, int $def, int $phy): int
    {
        $weights = match(true) {
            $pos === 'GK'                     => [0.05, 0.05, 0.10, 0.05, 0.40, 0.35],
            in_array($pos, ['CB','LB','RB'])  => [0.15, 0.05, 0.15, 0.10, 0.35, 0.20],
            in_array($pos, ['CDM'])           => [0.10, 0.10, 0.20, 0.15, 0.30, 0.15],
            in_array($pos, ['CM','CAM'])      => [0.10, 0.15, 0.30, 0.25, 0.10, 0.10],
            in_array($pos, ['LM','RM'])       => [0.20, 0.15, 0.20, 0.25, 0.10, 0.10],
            default                           => [0.20, 0.35, 0.10, 0.20, 0.05, 0.10],
        };

        $attrs = [$pac, $sho, $pas, $dri, $def, $phy];
        $sum   = 0;
        foreach ($attrs as $j => $val) {
            $sum += $val * $weights[$j];
        }

        return (int)round($sum);
    }

    private function calcValue(int $overall, int $age): int
    {
        $base = pow($overall / 50, 3) * 1_000_000;
        $ageFactor = match(true) {
            $age <= 23 => 1.3,
            $age <= 27 => 1.1,
            $age <= 30 => 0.9,
            default    => 0.6,
        };
        return (int)($base * $ageFactor);
    }

    private function randomName(): string
    {
        return $this->firstNames[array_rand($this->firstNames)]
             . ' '
             . $this->lastNames[array_rand($this->lastNames)];
    }

    private function getClubReputation(int $clubId): int
    {
        // Matches ClubSeeder reputation column (index 5)
        $reps = [85,84,80,78,76,74,72,71,70,69,68,67,66,65,64,63,62,61];
        return $reps[$clubId - 1] ?? 65;
    }
}
