<?php

$service = file_get_contents(__DIR__ . '/../app/Services/YouthAcademyService.php');
$rollover = file_get_contents(__DIR__ . '/../app/Services/AdminCompetitionService.php');
$controller = file_get_contents(__DIR__ . '/../app/Controllers/ClubFacilitiesController.php');
$view = file_get_contents(__DIR__ . '/../app/Views/club/facilities.php');
$schema = file_get_contents(__DIR__ . '/../database/schema.sql');
$migration = file_get_contents(__DIR__ . '/../database/migrations/20260412_youth_academy_new_player_generation_mvp.sql');

$serviceNeedles = [
    'class YouthAcademyService',
    'generateAnnualIntakeForSeason',
    'generateClubIntakeForSeason',
    'duplicate_prevented',
    'uniq_youth_intake_season_club',
    'academy_origin_club_id',
    'youth_intake_season_id',
    'is_academy_product',
    'PlayerCareerService::computeMarketValue',
    'getYouthAcademyLevel',
    'determineIntakeSize',
];
foreach ($serviceNeedles as $needle) {
    if (strpos($service, $needle) === false) {
        fwrite(STDERR, "Missing youth service fragment: {$needle}\n");
        exit(1);
    }
}

if (strpos($rollover, 'new YouthAcademyService') === false || strpos($rollover, 'generateAnnualIntakeForSeason') === false) {
    fwrite(STDERR, "Youth intake is not wired into season rollover flow\n");
    exit(1);
}

$uiNeedles = [
    'YouthAcademyService',
    'latest_intakes',
    'academy_players',
    'Youth Intake History',
    'Current Academy-Produced Players',
];
foreach ($uiNeedles as $needle) {
    if (strpos($controller, $needle) === false && strpos($view, $needle) === false) {
        fwrite(STDERR, "Missing youth UI/controller fragment: {$needle}\n");
        exit(1);
    }
}

if (strpos($schema, 'CREATE TABLE IF NOT EXISTS youth_intakes') === false) {
    fwrite(STDERR, "Missing youth_intakes schema table\n");
    exit(1);
}
if (strpos($schema, 'academy_origin_club_id INT NULL') === false) {
    fwrite(STDERR, "Missing players academy origin schema column\n");
    exit(1);
}
if (strpos($migration, 'CREATE TABLE IF NOT EXISTS youth_intakes') === false) {
    fwrite(STDERR, "Missing youth intake migration table\n");
    exit(1);
}

echo "youth_academy_generation_mvp_test: OK\n";
