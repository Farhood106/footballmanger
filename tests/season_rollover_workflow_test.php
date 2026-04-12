<?php

$service = file_get_contents(__DIR__ . '/../app/Services/AdminCompetitionService.php');
$controller = file_get_contents(__DIR__ . '/../app/Controllers/AdminCompetitionController.php');
$view = file_get_contents(__DIR__ . '/../app/Views/admin/competitions.php');
$routes = file_get_contents(__DIR__ . '/../public/index.php');
$schema = file_get_contents(__DIR__ . '/../database/schema.sql');
$migration = file_get_contents(__DIR__ . '/../database/migrations/20260411_season_rollover_mvp.sql');

$serviceNeedles = [
    'getSeasonRolloverReadiness',
    'finalizeSeason',
    'previewRollover',
    'applyRollover',
    'Season is not ready to finalize.',
    'Season must be finalized before applying rollover.',
    'Rollover has already been applied for this season.',
    'Target next-season already has manual participant assignments. Rollover apply is blocked for safety.',
    'season_rollover_logs',
    "status = 'APPLIED'",
];
foreach ($serviceNeedles as $needle) {
    if (strpos($service, $needle) === false) {
        fwrite(STDERR, "Missing rollover service fragment: {$needle}\n");
        exit(1);
    }
}

$controllerNeedles = [
    'public function finalizeSeason(int $id): void',
    'public function applyRollover(int $id): void',
];
foreach ($controllerNeedles as $needle) {
    if (strpos($controller, $needle) === false) {
        fwrite(STDERR, "Missing rollover controller fragment: {$needle}\n");
        exit(1);
    }
}

$routeNeedles = [
    "/admin/seasons/{id}/finalize', 'AdminCompetitionController@finalizeSeason",
    "/admin/seasons/{id}/rollover/apply', 'AdminCompetitionController@applyRollover",
];
foreach ($routeNeedles as $needle) {
    if (strpos($routes, $needle) === false) {
        fwrite(STDERR, "Missing rollover route: {$needle}\n");
        exit(1);
    }
}

$viewNeedles = ['Rollover Readiness', 'Finalize Season', 'Apply Rollover', 'Rollover Plan Preview'];
foreach ($viewNeedles as $needle) {
    if (strpos($view, $needle) === false) {
        fwrite(STDERR, "Missing rollover view fragment: {$needle}\n");
        exit(1);
    }
}

if (strpos($schema, 'CREATE TABLE IF NOT EXISTS season_rollover_logs') === false) {
    fwrite(STDERR, "Missing season_rollover_logs schema table\n");
    exit(1);
}
if (strpos($migration, 'CREATE TABLE IF NOT EXISTS season_rollover_logs') === false) {
    fwrite(STDERR, "Missing season rollover migration table\n");
    exit(1);
}

echo "season_rollover_workflow_test: OK\n";
