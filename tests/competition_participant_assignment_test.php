<?php

$serviceFile = __DIR__ . '/../app/Services/AdminCompetitionService.php';
$controllerFile = __DIR__ . '/../app/Controllers/AdminCompetitionController.php';
$viewFile = __DIR__ . '/../app/Views/admin/competitions.php';
$routeFile = __DIR__ . '/../public/index.php';

$service = file_get_contents($serviceFile);
$controller = file_get_contents($controllerFile);
$view = file_get_contents($viewFile);
$routes = file_get_contents($routeFile);


$migrationFile = __DIR__ . '/../database/migrations/20260411_participant_assignment_mvp.sql';
$schemaFile = __DIR__ . '/../database/schema.sql';
$migration = file_get_contents($migrationFile);
$schema = file_get_contents($schemaFile);

if (strpos($migration, "ALTER TABLE club_seasons ADD COLUMN entry_type") === false) {
    fwrite(STDERR, "Missing entry_type migration in participant migration file
");
    exit(1);
}
if (strpos($schema, "entry_type ENUM('direct','promoted','relegated','champion','qualified','wildcard')") === false) {
    fwrite(STDERR, "Missing entry_type schema definition for club_seasons
");
    exit(1);
}

$serviceNeedles = [
    'addSeasonParticipant',
    'removeSeasonParticipant',
    'getSeasonParticipants',
    'Club is already assigned to this season.',
    'Cannot remove participant after fixtures are generated.',
    'No explicit participants assigned to this season. Assign clubs before generating fixtures.',
    'Participant count mismatch: expected',
    "SELECT club_id FROM club_seasons WHERE season_id = ?",
];

foreach ($serviceNeedles as $needle) {
    if (strpos($service, $needle) === false) {
        fwrite(STDERR, "Missing service participant guard fragment: {$needle}\n");
        exit(1);
    }
}

$controllerNeedles = [
    'public function addParticipant(int $seasonId): void',
    'public function removeParticipant(int $seasonId, int $clubId): void',
    'requireAdmin();',
    'addSeasonParticipant',
    'removeSeasonParticipant',
];

foreach ($controllerNeedles as $needle) {
    if (strpos($controller, $needle) === false) {
        fwrite(STDERR, "Missing controller participant wiring fragment: {$needle}\n");
        exit(1);
    }
}

$routeNeedles = [
    "/admin/seasons/{season_id}/participants/add', 'AdminCompetitionController@addParticipant",
    "/admin/seasons/{season_id}/participants/{club_id}/remove', 'AdminCompetitionController@removeParticipant",
];

foreach ($routeNeedles as $needle) {
    if (strpos($routes, $needle) === false) {
        fwrite(STDERR, "Missing participant route: {$needle}\n");
        exit(1);
    }
}

$viewNeedles = [
    'Participants:',
    'Add Participant',
    'Entry Type',
    'No participants assigned.',
    'participants/add',
    'participants/',
    '/remove',
];

foreach ($viewNeedles as $needle) {
    if (strpos($view, $needle) === false) {
        fwrite(STDERR, "Missing participant UI fragment: {$needle}\n");
        exit(1);
    }
}

echo "competition_participant_assignment_test: OK\n";
