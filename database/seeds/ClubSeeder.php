<?php

class ClubSeeder
{
    // 18 Iranian Premier League clubs
    private array $clubs = [
        ['پرسپولیس',       'تهران',    'پرسپولیس',    '#D32F2F', '#FFFFFF', 85, 50_000_000],
        ['استقلال',        'تهران',    'استقلال',     '#1565C0', '#FFFFFF', 84, 48_000_000],
        ['سپاهان',         'اصفهان',   'سپاهان',      '#F57F17', '#000000', 80, 35_000_000],
        ['تراکتور',        'تبریز',    'تراکتور',     '#E53935', '#FFFFFF', 78, 30_000_000],
        ['فولاد خوزستان',  'اهواز',    'فولاد',       '#BF360C', '#FFFFFF', 76, 28_000_000],
        ['ذوب‌آهن',        'اصفهان',   'ذوب‌آهن',     '#2E7D32', '#FFFFFF', 74, 25_000_000],
        ['گل‌گهر سیرجان',  'سیرجان',   'گل‌گهر',      '#6A1B9A', '#FFFFFF', 72, 22_000_000],
        ['نساجی مازندران', 'قائمشهر',  'نساجی',       '#1B5E20', '#FFFF00', 71, 20_000_000],
        ['هوادار',         'تهران',    'هوادار',      '#0D47A1', '#FFFFFF', 70, 18_000_000],
        ['پیکان',          'تهران',    'پیکان',       '#37474F', '#FFFFFF', 69, 17_000_000],
        ['آلومینیوم اراک', 'اراک',     'آلومینیوم',   '#78909C', '#000000', 68, 16_000_000],
        ['شمس آذر',        'قزوین',    'شمس آذر',     '#FF6F00', '#FFFFFF', 67, 15_000_000],
        ['ملوان',          'بندرانزلی','ملوان',       '#00695C', '#FFFFFF', 66, 14_000_000],
        ['مس رفسنجان',     'رفسنجان',  'مس',          '#E65100', '#FFFFFF', 65, 13_000_000],
        ['خیبر خرم‌آباد',  'خرم‌آباد', 'خیبر',        '#4527A0', '#FFFFFF', 64, 12_000_000],
        ['صنعت نفت آبادان','آبادان',   'صنعت نفت',    '#1A237E', '#FFFFFF', 63, 12_000_000],
        ['چادرملو',        'اردکان',   'چادرملو',     '#880E4F', '#FFFFFF', 62, 11_000_000],
        ['پارس جنوبی',     'بوشهر',    'پارس',        '#004D40', '#FFFFFF', 61, 10_000_000],
    ];

    public function __construct(private PDO $db) {}

    public function run(): void
    {
        $this->db->exec("DELETE FROM league_standings");
        $this->db->exec("DELETE FROM club_finances");
        $this->db->exec("DELETE FROM clubs");

        $clubStmt = $this->db->prepare("
            INSERT INTO clubs
                (id, name, city, short_name, primary_color, secondary_color, reputation, budget, competition_id, founded_year)
            VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, 1, ?)
        ");

        $financeStmt = $this->db->prepare("
            INSERT INTO club_finances (club_id, balance, weekly_wages, season_id)
            VALUES (?, ?, ?, 1)
        ");

        $standingStmt = $this->db->prepare("
            INSERT INTO league_standings
                (competition_id, season_id, club_id, played, won, drawn, lost,
                 goals_for, goals_against, points)
            VALUES (1, 1, ?, 0, 0, 0, 0, 0, 0, 0)
        ");

        foreach ($this->clubs as $i => $club) {
            $id         = $i + 1;
            $founded    = 1968 + ($i * 3);
            $weeklyWage = (int)($club[6] * 0.002); // ~0.2% of budget per week

            $clubStmt->execute([
                $id,
                $club[0], // name
                $club[1], // city
                $club[2], // short_name
                $club[3], // primary_color
                $club[4], // secondary_color
                $club[5], // reputation
                $club[6], // budget
                $founded,
            ]);

            $financeStmt->execute([$id, $club[6], $weeklyWage]);
            $standingStmt->execute([$id]);
        }

        echo "  18 clubs seeded with finances and standings.\n";
    }
}
