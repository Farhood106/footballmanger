<?php

class AbilitySeeder
{
    private array $abilities = [
        // Striker abilities
        ['deadly_finisher',   'گلزن کشنده',      'نرخ تبدیل موقعیت به گل +15%',          'STRIKER',   1, 50,  0],
        ['aerial_threat',     'تهدید هوایی',     'قدرت هدر +20 در ضربات سر',             'STRIKER',   1,  0, 30],
        ['speed_demon',       'شیطان سرعت',      'سرعت مؤثر +10 در فضای باز',            'STRIKER',   1,  0, 20],
        ['poacher',           'شکارچی',          'گل از موقعیت‌های نزدیک +20%',          'STRIKER',   2, 80,  0],
        ['hat_trick_hero',    'قهرمان هت‌تریک',  'احتمال گل سوم در بازی +25%',           'STRIKER',   3,100,  0],

        // Midfielder abilities
        ['vision_master',     'استاد دید',       'دقت پاس کلیدی +15%',                   'MIDFIELDER',1,  0, 50],
        ['engine',            'موتور تیم',       'خستگی ۲۰% کمتر',                       'MIDFIELDER',1,  0, 60],
        ['long_shot',         'شوت از راه دور',  'احتمال گل از بیرون محوطه +20%',        'MIDFIELDER',2, 30,  0],
        ['set_piece_expert',  'متخصص ضربات ثابت','دقت کرنر و فری‌کیک +25%',             'MIDFIELDER',2,  0, 80],

        // Defender abilities
        ['rock_solid',        'سنگ محکم',        'احتمال کلین‌شیت +10%',                 'DEFENDER',  1,  0, 60],
        ['aerial_defender',   'مدافع هوایی',     'برنده ۸۰% دوئل‌های هوایی',            'DEFENDER',  1,  0, 40],
        ['sweeper',           'لیبرو',           'رهگیری حملات سریع +20%',               'DEFENDER',  2,  0, 80],
        ['leader',            'رهبر دفاعی',      'روحیه تیم +5 در بازی‌های حساس',       'DEFENDER',  3,  0,100],

        // Goalkeeper abilities
        ['penalty_stopper',   'قهرمان پنالتی',   'احتمال دفع پنالتی +20%',               'GOALKEEPER',1,  0, 20],
        ['sweeper_keeper',    'دروازه‌بان لیبرو', 'دفع توپ‌های بلند +15%',               'GOALKEEPER',2,  0, 60],
        ['reflexes',          'رفلکس‌های برق',   'دفع شوت‌های نزدیک +15%',              'GOALKEEPER',1,  0, 30],

        // Universal abilities
        ['captain',           'کاپیتان',         'روحیه کل تیم +5',                      'ALL',       3,  0,100],
        ['iron_man',          'مرد آهنین',       'احتمال مصدومیت ۵۰% کمتر',             'ALL',       2,  0, 80],
        ['young_talent',      'استعداد جوان',    'رشد سریع‌تر ویژگی‌ها',                'ALL',       1,  0,  0],
        ['veteran',           'کهنه‌کار',        'تجربه: روحیه در بازی‌های سخت +10',    'ALL',       2,  0,150],
    ];

    public function __construct(private PDO $db) {}

    public function run(): void
    {
        $this->db->exec("DELETE FROM abilities");

        $stmt = $this->db->prepare("
            INSERT INTO abilities
                (code, name, description, position_type, level,
                 goals_required, appearances_required)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        foreach ($this->abilities as $ab) {
            $stmt->execute($ab);
        }

        echo "  " . count($this->abilities) . " abilities seeded.\n";
    }
}
