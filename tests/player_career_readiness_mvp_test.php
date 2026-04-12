<?php
require_once __DIR__ . '/../app/Services/PlayerCareerService.php';

$careerService = file_get_contents(__DIR__ . '/../app/Services/PlayerCareerService.php');
$matchEngine = file_get_contents(__DIR__ . '/../app/Services/MatchEngine.php');
$daily = file_get_contents(__DIR__ . '/../app/Services/DailyCycleOrchestrator.php');
$playerModel = file_get_contents(__DIR__ . '/../app/Models/PlayerModel.php');
$schema = file_get_contents(__DIR__ . '/../database/schema.sql');
$migration = file_get_contents(__DIR__ . '/../database/migrations/20260412_player_career_readiness_mvp.sql');

$needles = [
    [$careerService, 'computeDevelopmentSignal'],
    [$careerService, 'computeMarketValue'],
    [$careerService, 'applyDailyRecoveryAndDrift'],
    [$careerService, 'runDailyDevelopmentAndValuation'],
    [$careerService, 'applyPostMatchPlayerUpdate'],
    [$careerService, 'player_career_history'],
    [$matchEngine, 'private PlayerCareerService $playerCareer;'],
    [$matchEngine, 'applyPostMatchPlayerUpdate'],
    [$matchEngine, 'upsertCareerHistoryFromSeasonStats'],
    [$matchEngine, 'starts = starts + :starts'],
    [$daily, 'applyDailyRecoveryAndDrift'],
    [$daily, 'runDailyDevelopmentAndValuation'],
    [$playerModel, "'fitness'"],
    [$playerModel, 'career_minutes'],
    [$schema, 'fitness INT DEFAULT 100'],
    [$schema, 'morale_score INT DEFAULT 70'],
    [$schema, 'CREATE TABLE IF NOT EXISTS player_career_history'],
    [$migration, 'ALTER TABLE players ADD COLUMN fitness'],
    [$migration, 'CREATE TABLE IF NOT EXISTS player_career_history'],
];

foreach ($needles as [$haystack, $needle]) {
    if (strpos($haystack, $needle) === false) {
        fwrite(STDERR, "Missing player career/readiness fragment: {$needle}\n");
        exit(1);
    }
}

// Behavioral sanity checks for deterministic formulas
$devYoung = PlayerCareerService::computeDevelopmentSignal(20, 70, 86, 8, 85, 80, false);
$devOld = PlayerCareerService::computeDevelopmentSignal(34, 74, 76, 2, 55, 50, false);
if (!($devYoung > $devOld)) {
    fwrite(STDERR, "Development age-profile sanity failed\n");
    exit(1);
}

$valueYoung = PlayerCareerService::computeMarketValue(78, 88, 21, 7, 88, 82, false, 40);
$valueOld = PlayerCareerService::computeMarketValue(78, 80, 34, 7, 88, 82, false, 240);
if (!($valueYoung > $valueOld)) {
    fwrite(STDERR, "Market-value age profile sanity failed\n");
    exit(1);
}

echo "player_career_readiness_mvp_test: OK\n";
