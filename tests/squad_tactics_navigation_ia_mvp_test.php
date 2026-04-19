<?php

$routes = file_get_contents(__DIR__ . '/../public/index.php');
$header = file_get_contents(__DIR__ . '/../app/Views/layout/header.php');
$squadIndex = file_get_contents(__DIR__ . '/../app/Views/squad/index.php');
$tacticsView = file_get_contents(__DIR__ . '/../app/Views/squad/tactics.php');
$playerDetail = file_get_contents(__DIR__ . '/../app/Views/squad/player-detail.php');

$needles = [
    [$routes, "\$router->get('/squad', 'SquadController@index');"],
    [$routes, "\$router->get('/squad/players', 'SquadController@index');"],
    [$routes, "\$router->get('/squad/tactics', 'SquadController@tactics');"],
    [$routes, "\$router->get('/squad/lineup', 'SquadController@tactics');"],
    [$header, 'اسکواد / بازیکنان'],
    [$header, 'تاکتیک / ترکیب'],
    [$squadIndex, 'اسکواد / مدیریت بازیکنان'],
    [$squadIndex, 'رفتن به تاکتیک / ترکیب'],
    [$tacticsView, 'تاکتیک / ترکیب / فرمیشن'],
    [$tacticsView, 'بازگشت به اسکواد / بازیکنان'],
    [$playerDetail, 'بازگشت به اسکواد / بازیکنان'],
    [$playerDetail, 'رفتن به تاکتیک / ترکیب'],
];

foreach ($needles as [$haystack, $needle]) {
    if (strpos($haystack, $needle) === false) {
        fwrite(STDERR, "Missing squad/tactics IA fragment: {$needle}\n");
        exit(1);
    }
}

echo "squad_tactics_navigation_ia_mvp_test: OK\n";
