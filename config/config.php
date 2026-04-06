<?php
// config/config.php

return [
    'db' => [
        'host' => 'localhost',
        'name' => 'itshomar_footballgame',
        'user' => 'itshomar_footballgame',
        'pass' => 'Om65%Z+RFG]8,k;O',
        'charset' => 'utf8mb4'
    ],

    'app' => [
        'name' => 'Football Manager',
        'url' => 'http://itshomar.ir/',
        'timezone' => 'Asia/Tehran',
        'locale' => 'fa'
    ],

    'auth' => [
        'session_lifetime' => 86400, // 24 ساعت
        'password_min_length' => 6
    ],

    'game' => [
        'timezone' => 'Asia/Tehran',

        'match_times' => [
            'first_match' => '12:00',
            'second_match' => '18:00'
        ],

        'training_windows' => [
            ['start' => '07:00', 'end' => '10:00'],
            ['start' => '13:00', 'end' => '14:00'],
            ['start' => '19:00', 'end' => '21:00']
        ],

        // چرخه روزانه برای هر «روز بازی» که معادل یک هفته فوتبالی در دنیای واقعی است
        'daily_cycle' => [
            'phases' => [
                'one_match' => [
                    [
                        'key' => 'LINEUP_1_SETUP',
                        'label' => 'تنظیم ترکیب بازی اول',
                        'start' => '07:00',
                        'end' => '10:00',
                        'is_locked' => false,
                        'actions' => ['SET_LINEUP_1', 'SET_TACTIC_1', 'SET_TRAINING_PRE_MATCH']
                    ],
                    [
                        'key' => 'MATCH_1_LIVE',
                        'label' => 'برگزاری بازی اول',
                        'start' => '12:00',
                        'end' => '12:30',
                        'is_locked' => true,
                        'actions' => ['SIMULATE_MATCH_1', 'GENERATE_REPORT_1']
                    ],
                    [
                        'key' => 'POST_MATCH_1_TRAINING',
                        'label' => 'تمرین بعد از بازی اول',
                        'start' => '13:00',
                        'end' => '14:00',
                        'is_locked' => false,
                        'actions' => ['SET_TRAINING_POST_MATCH', 'VIEW_RECOVERY']
                    ],
                    [
                        'key' => 'PRE_NEXT_DAY_TRAINING',
                        'label' => 'تمرین برای بازی فردا',
                        'start' => '19:00',
                        'end' => '21:00',
                        'is_locked' => false,
                        'actions' => ['SET_TRAINING_NEXT_DAY', 'ROTATE_SQUAD']
                    ],
                ],
                'two_matches' => [
                    [
                        'key' => 'LINEUP_1_SETUP',
                        'label' => 'تنظیم ترکیب بازی اول',
                        'start' => '07:00',
                        'end' => '10:00',
                        'is_locked' => false,
                        'actions' => ['SET_LINEUP_1', 'SET_TACTIC_1', 'SET_TRAINING_PRE_MATCH']
                    ],
                    [
                        'key' => 'MATCH_1_LIVE',
                        'label' => 'برگزاری بازی اول',
                        'start' => '12:00',
                        'end' => '12:30',
                        'is_locked' => true,
                        'actions' => ['SIMULATE_MATCH_1', 'GENERATE_REPORT_1']
                    ],
                    [
                        'key' => 'TRAINING_BEFORE_MATCH_2',
                        'label' => 'تمرین پیش از بازی دوم',
                        'start' => '13:00',
                        'end' => '14:00',
                        'is_locked' => false,
                        'actions' => ['SET_TRAINING_2', 'ADJUST_FITNESS']
                    ],
                    [
                        'key' => 'LINEUP_2_SETUP',
                        'label' => 'تنظیم ترکیب بازی دوم',
                        'start' => '14:00',
                        'end' => '17:00',
                        'is_locked' => false,
                        'actions' => ['SET_LINEUP_2', 'SET_TACTIC_2', 'REVIEW_MATCH_1_IMPACT']
                    ],
                    [
                        'key' => 'MATCH_2_LIVE',
                        'label' => 'برگزاری بازی دوم',
                        'start' => '18:00',
                        'end' => '18:30',
                        'is_locked' => true,
                        'actions' => ['SIMULATE_MATCH_2', 'GENERATE_REPORT_2']
                    ],
                    [
                        'key' => 'PRE_NEXT_DAY_TRAINING',
                        'label' => 'تمرین برای بازی فردا',
                        'start' => '19:00',
                        'end' => '21:00',
                        'is_locked' => false,
                        'actions' => ['SET_TRAINING_NEXT_DAY', 'ROTATE_SQUAD']
                    ],
                ]
            ]
        ]
    ]
];
