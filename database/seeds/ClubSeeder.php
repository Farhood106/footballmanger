<?php

class ClubSeeder
{
    private array $clubs = [
        ['پرسپولیس', 'تهران', 'PER', 85, 50_000_000],
        ['استقلال', 'تهران', 'EST', 84, 48_000_000],
        ['سپاهان', 'اصفهان', 'SEP', 80, 35_000_000],
        ['تراکتور', 'تبریز', 'TRA', 78, 30_000_000],
        ['فولاد خوزستان', 'اهواز', 'FUL', 76, 28_000_000],
        ['ذوب‌آهن', 'اصفهان', 'ZOB', 74, 25_000_000],
        ['گل‌گهر سیرجان', 'سیرجان', 'GOL', 72, 22_000_000],
        ['نساجی مازندران', 'قائمشهر', 'NAS', 71, 20_000_000],
        ['هوادار', 'تهران', 'HOV', 70, 18_000_000],
        ['پیکان', 'تهران', 'PEY', 69, 17_000_000],
        ['آلومینیوم اراک', 'اراک', 'ALO', 68, 16_000_000],
        ['شمس آذر', 'قزوین', 'SHA', 67, 15_000_000],
        ['ملوان', 'بندرانزلی', 'MAL', 66, 14_000_000],
        ['مس رفسنجان', 'رفسنجان', 'MES', 65, 13_000_000],
        ['خیبر خرم‌آباد', 'خرم‌آباد', 'KHI', 64, 12_000_000],
        ['صنعت نفت آبادان', 'آبادان', 'SNA', 63, 12_000_000],
        ['چادرملو', 'اردکان', 'CHA', 62, 11_000_000],
        ['پارس جنوبی', 'بوشهر', 'PAR', 61, 10_000_000],
    ];

    public function __construct(private PDO $db) {}

    public function run(): void
    {
        $this->db->exec("DELETE FROM standings");
        $this->db->exec("DELETE FROM club_finance_ledger");
        $this->db->exec("DELETE FROM clubs");

        $clubStmt = $this->db->prepare(
            "INSERT INTO clubs (id, name, short_name, country, city, founded, reputation, balance, stadium_name, stadium_capacity)
             VALUES (?, ?, ?, 'Iran', ?, ?, ?, ?, ?, ?)"
        );

        $standingStmt = $this->db->prepare(
            "INSERT INTO standings (season_id, club_id, position, played, won, drawn, lost, goals_for, goals_against, goal_diff, points)
             VALUES (1, ?, 0, 0, 0, 0, 0, 0, 0, 0, 0)"
        );

        $ledgerStmt = $this->db->prepare(
            "INSERT INTO club_finance_ledger (club_id, season_id, entry_type, amount, description)
             VALUES (?, 1, 'SPONSOR', ?, 'Initial balance seeding')"
        );

        foreach ($this->clubs as $i => $club) {
            $id = $i + 1;
            $founded = 1960 + $i;
            $stadium = $club[0] . ' Stadium';
            $capacity = 18000 + ($i * 1000);

            $clubStmt->execute([$id, $club[0], $club[2], $club[1], $founded, $club[3], $club[4], $stadium, $capacity]);
            $standingStmt->execute([$id]);
            $ledgerStmt->execute([$id, $club[4]]);
        }

        echo "  18 clubs seeded with balances and standings.\n";
    }
}
