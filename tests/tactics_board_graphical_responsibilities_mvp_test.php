<?php

$tacticModel = file_get_contents(__DIR__ . '/../app/Models/TacticModel.php');
$squadController = file_get_contents(__DIR__ . '/../app/Controllers/SquadController.php');
$tacticsView = file_get_contents(__DIR__ . '/../app/Views/squad/tactics.php');
$matchEngine = file_get_contents(__DIR__ . '/../app/Services/MatchEngine.php');
$schema = file_get_contents(__DIR__ . '/../database/schema.sql');
$migration = file_get_contents(__DIR__ . '/../database/migrations/20260418_tactics_board_responsibilities_mvp.sql');

$needles = [
    [$tacticModel, "'LM__1'"],
    [$tacticModel, "'RM__1'"],
    [$tacticModel, "'position_slot' => 'LM'"],
    [$tacticModel, "'position_slot' => 'RM'"],
    [$tacticModel, "'LM' =>"],
    [$tacticModel, "'RM' =>"],
    [$tacticModel, 'board_x'],
    [$tacticModel, 'board_y'],
    [$tacticModel, 'selected_candidate'],
    [$tacticModel, 'buildResponsibilityRankings'],
    [$tacticModel, 'calculateResponsibilityScore'],
    [$squadController, 'captain'],
    [$squadController, 'penalty_taker'],
    [$squadController, 'freekick_taker'],
    [$squadController, 'corner_taker'],
    [$tacticsView, 'tactics-pitch'],
    [$tacticsView, 'بورد گرافیکی تاکتیک'],
    [$tacticsView, 'مسئولیت‌های کلیدی تیم'],
    [$tacticsView, 'Score'],
    [$tacticsView, '⭐'],
    [$tacticsView, 'lineup-slot-select'],
    [$schema, "'LM','RM'"],
    [$schema, 'corner_taker INT'],
    [$schema, 'freekick_taker INT'],
    [$schema, 'penalty_taker INT'],
    [$schema, 'captain INT'],
    [$migration, "ALTER TABLE players MODIFY COLUMN position ENUM('GK','LB','RB','CB','LWB','RWB','CDM','CM','CAM','LM','RM','LW','RW','ST','CF') NOT NULL"],
    [$migration, 'COLUMN_NAME = \'captain\''],
    [$migration, 'COLUMN_NAME = \'penalty_taker\''],
    [$migration, 'COLUMN_NAME = \'freekick_taker\''],
    [$migration, 'COLUMN_NAME = \'corner_taker\''],
    [$matchEngine, "'captain' => null"],
    [$matchEngine, "'penalty_taker' => null"],
    [$matchEngine, "'freekick_taker' => null"],
    [$matchEngine, "'corner_taker' => null"],
    [$matchEngine, 'set_piece_mod'],
    [$matchEngine, 'buildResponsibilityImpact'],
];

foreach ($needles as [$haystack, $needle]) {
    if (strpos($haystack, $needle) === false) {
        fwrite(STDERR, "Missing tactics board/responsibility fragment: {$needle}\n");
        exit(1);
    }
}

echo "tactics_board_graphical_responsibilities_mvp_test: OK\n";
