<?php

$competitionController = file_get_contents(__DIR__ . '/../app/Controllers/CompetitionController.php');
$matchController = file_get_contents(__DIR__ . '/../app/Controllers/MatchController.php');
$matchDetailView = file_get_contents(__DIR__ . '/../app/Views/match/detail.php');
$playerDetailView = file_get_contents(__DIR__ . '/../app/Views/squad/player-detail.php');
$historyService = file_get_contents(__DIR__ . '/../app/Services/WorldHistoryService.php');
$managerAppModel = file_get_contents(__DIR__ . '/../app/Models/ManagerApplicationModel.php');
$adminCompetitionService = file_get_contents(__DIR__ . '/../app/Services/AdminCompetitionService.php');
$schema = file_get_contents(__DIR__ . '/../database/schema.sql');
$migration = file_get_contents(__DIR__ . '/../database/migrations/20260415_runtime_hardening_core_tables.sql');

$needles = [
    [$competitionController, "'competition' =>"],
    [$competitionController, "'userClubId' =>"],
    [$competitionController, "'matchday' =>"],
    [$competitionController, "'home_goals' =>"],
    [$competitionController, "'away_goals' =>"],
    [$matchController, "'events' =>"],
    [$matchController, "'matchStats' =>"],
    [$matchController, "'ratings' =>"],
    [$matchDetailView, "home_score"],
    [$matchDetailView, "away_score"],
    [$matchDetailView, "\$e['type']"],
    [$playerDetailView, "season_stats"],
    [$playerDetailView, "career_stats"],
    [$historyService, "CONCAT(p.first_name, ' ', p.last_name) AS player_name"],
    [$schema, 'CREATE TABLE IF NOT EXISTS player_awards'],
    [$schema, 'CREATE TABLE IF NOT EXISTS club_honors'],
    [$schema, 'CREATE TABLE IF NOT EXISTS club_records'],
    [$schema, 'CREATE TABLE IF NOT EXISTS club_legends'],
    [$schema, 'CREATE TABLE IF NOT EXISTS admin_operation_logs'],
    [$migration, 'CREATE TABLE IF NOT EXISTS player_awards'],
    [$migration, 'CREATE TABLE IF NOT EXISTS admin_operation_logs'],
];

foreach ($needles as [$haystack, $needle]) {
    if (strpos($haystack, $needle) === false) {
        fwrite(STDERR, "Missing expected hardening fragment: {$needle}\n");
        exit(1);
    }
}

if (strpos($playerDetailView, "\$stats[") !== false) {
    fwrite(STDERR, "Legacy player detail variable \$stats still present\n");
    exit(1);
}

if (strpos($playerDetailView, "\$player['name']") !== false) {
    fwrite(STDERR, "Legacy player detail name key still present\n");
    exit(1);
}

if (strpos($managerAppModel, "\$this->db->commit();\n            \$this->db->commit();") !== false) {
    fwrite(STDERR, "Duplicate commit pattern still present in ManagerApplicationModel\n");
    exit(1);
}

if (strpos($adminCompetitionService, "\$inserted++;\n            \$inserted++;") !== false) {
    fwrite(STDERR, "Duplicate inserted counter pattern still present in AdminCompetitionService\n");
    exit(1);
}

echo "hardening_runtime_integration_test: OK\n";
