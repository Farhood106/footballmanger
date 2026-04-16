<?php

$tacticModel = file_get_contents(__DIR__ . '/../app/Models/TacticModel.php');
$squadController = file_get_contents(__DIR__ . '/../app/Controllers/SquadController.php');
$tacticsView = file_get_contents(__DIR__ . '/../app/Views/squad/tactics.php');
$matchEngine = file_get_contents(__DIR__ . '/../app/Services/MatchEngine.php');
$orchestrator = file_get_contents(__DIR__ . '/../app/Services/DailyCycleOrchestrator.php');
$aiService = file_get_contents(__DIR__ . '/../app/Services/AIClubManagementService.php');
$schema = file_get_contents(__DIR__ . '/../database/schema.sql');
$migration = file_get_contents(__DIR__ . '/../database/migrations/20260416_lineup_builder_position_ratings_mvp.sql');

$needles = [
    [$tacticModel, "'4-4-2'"],
    [$tacticModel, "'4-3-3'"],
    [$tacticModel, "'4-2-3-1'"],
    [$tacticModel, "'3-5-2'"],
    [$tacticModel, 'calculatePositionRating'],
    [$tacticModel, 'positionWeights'],
    [$tacticModel, 'buildLineupSelectionData'],
    [$tacticModel, 'slot_order'],
    [$squadController, 'یک بازیکن نمی‌تواند در چند اسلات همزمان قرار گیرد'],
    [$squadController, 'برای همه اسلات‌ها بازیکن انتخاب کنید'],
    [$tacticsView, 'Lineup Builder'],
    [$tacticsView, 'Out of Position'],
    [$matchEngine, 'match_lineups'],
    [$matchEngine, 'lineup_position'],
    [$orchestrator, 'tl.slot_order'],
    [$aiService, "'slot_order' =>"],
    [$schema, 'slot_order TINYINT NOT NULL DEFAULT 1'],
    [$schema, 'unique_active_lineup_slot_order'],
    [$migration, 'ADD COLUMN slot_order TINYINT NOT NULL DEFAULT 1'],
    [$migration, 'unique_active_lineup_slot_order'],
];

foreach ($needles as [$haystack, $needle]) {
    if (strpos($haystack, $needle) === false) {
        fwrite(STDERR, "Missing tactics lineup builder fragment: {$needle}\n");
        exit(1);
    }
}

echo "tactics_lineup_builder_mvp_test: OK\n";
