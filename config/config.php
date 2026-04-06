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
        'match_times' => [
            'first_match' => '12:00',
            'second_match' => '18:00'
        ],
        'training_windows' => [
            ['start' => '07:00', 'end' => '10:00'],
            ['start' => '13:00', 'end' => '14:00'],
            ['start' => '19:00', 'end' => '21:00']
        ]
    ]
];
