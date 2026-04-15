<?php

$schema = file_get_contents(__DIR__ . '/../database/schema.sql');
$migration = file_get_contents(__DIR__ . '/../database/migrations/20260415_matchday_squad_depth_mvp.sql');
$playerCareer = file_get_contents(__DIR__ . '/../app/Services/PlayerCareerService.php');
$matchEngine = file_get_contents(__DIR__ . '/../app/Services/MatchEngine.php');
$playerModel = file_get_contents(__DIR__ . '/../app/Models/PlayerModel.php');
$squadController = file_get_contents(__DIR__ . '/../app/Controllers/SquadController.php');
$squadView = file_get_contents(__DIR__ . '/../app/Views/squad/index.php');
$playerView = file_get_contents(__DIR__ . '/../app/Views/squad/player-detail.php');
$ai = file_get_contents(__DIR__ . '/../app/Services/AIClubManagementService.php');
$routes = file_get_contents(__DIR__ . '/../public/index.php');
$schemaVerifier = file_get_contents(__DIR__ . '/../app/Core/SchemaSafetyVerifier.php');

$checks = [
    [$schema, "squad_role ENUM('KEY_PLAYER','REGULAR_STARTER','ROTATION','BENCH','PROSPECT')"],
    [$schema, 'last_played_at DATETIME NULL'],
    [$schema, 'last_minutes_played INT DEFAULT 0'],
    [$migration, 'ALTER TABLE players ADD COLUMN squad_role'],
    [$migration, 'ALTER TABLE players ADD COLUMN last_played_at'],
    [$migration, 'ALTER TABLE players ADD COLUMN last_minutes_played'],
    [$playerCareer, 'ROLE_TARGET_MINUTES'],
    [$playerCareer, 'inactivityMoraleDelta'],
    [$playerCareer, 'roleExpectationDelta'],
    [$playerCareer, 'calculateStartLoadPressure'],
    [$playerCareer, 'last_played_at = ?, last_minutes_played = ?'],
    [$matchEngine, 'applyPostMatchPlayerUpdate($player, $rating, $started, $minutesPlayed, $injury !== false, $playedAt)'],
    [$playerModel, 'setSquadRoleForClub'],
    [$squadController, 'saveSquadRole'],
    [$routes, "/squad/role/save"],
    [$squadView, "name=\"squad_role\""],
    [$squadView, 'inactivity_warning'],
    [$playerView, 'نقش در تیم'],
    [$ai, 'last_minutes_played'],
    [$ai, 'heavyMinutesPenalty'],
    [$schemaVerifier, "'squad_role', 'last_played_at', 'last_minutes_played'"],
];

foreach ($checks as [$haystack, $needle]) {
    if (strpos($haystack, $needle) === false) {
        fwrite(STDERR, "Missing required squad depth MVP fragment: {$needle}\n");
        exit(1);
    }
}

echo "squad_matchday_depth_mvp_test: OK\n";
