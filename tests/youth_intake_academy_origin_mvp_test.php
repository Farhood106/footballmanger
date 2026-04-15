<?php

$youthService = file_get_contents(__DIR__ . '/../app/Services/YouthIntakeService.php');
$rolloverService = file_get_contents(__DIR__ . '/../app/Services/AdminCompetitionService.php');
$squadController = file_get_contents(__DIR__ . '/../app/Controllers/SquadController.php');
$squadView = file_get_contents(__DIR__ . '/../app/Views/squad/index.php');
$playerView = file_get_contents(__DIR__ . '/../app/Views/squad/player-detail.php');
$schema = file_get_contents(__DIR__ . '/../database/schema.sql');
$migration = file_get_contents(__DIR__ . '/../database/migrations/20260415_youth_intake_academy_origin_mvp.sql');
$schemaVerifier = file_get_contents(__DIR__ . '/../app/Core/SchemaSafetyVerifier.php');

$needles = [
    [$youthService, 'class YouthIntakeService'],
    [$youthService, 'generateForSeason'],
    [$youthService, 'generateForClub'],
    [$youthService, 'resolveAcademyLevel'],
    [$youthService, 'uniq_club_season_intake'],
    [$youthService, "'is_academy_origin' => 1"],
    [$youthService, "'academy_origin_club_id' => \$clubId"],
    [$youthService, "'academy_intake_season_id' => \$seasonId"],
    [$rolloverService, 'new YouthIntakeService'],
    [$rolloverService, "generateForSeason("],
    [$rolloverService, "'ROLLOVER_APPLY'"],
    [$squadController, 'youth_intakes'],
    [$squadView, 'ورودی‌های اخیر آکادمی'],
    [$squadView, 'academy_origin'],
    [$playerView, 'منشأ آکادمی'],
    [$schema, 'is_academy_origin BOOLEAN DEFAULT 0'],
    [$schema, 'academy_origin_club_id INT NULL'],
    [$schema, 'academy_intake_season_id INT NULL'],
    [$schema, 'academy_intake_batch_key VARCHAR(64) NULL'],
    [$schema, 'CREATE TABLE IF NOT EXISTS youth_intake_logs'],
    [$migration, 'CREATE TABLE IF NOT EXISTS youth_intake_logs'],
    [$migration, 'UNIQUE KEY uniq_club_season_intake'],
    [$schemaVerifier, 'youth_intake_logs'],
    [$schemaVerifier, 'academy_intake_batch_key'],
];

foreach ($needles as [$haystack, $needle]) {
    if (strpos($haystack, $needle) === false) {
        fwrite(STDERR, "Missing youth intake/origin fragment: {$needle}\n");
        exit(1);
    }
}

echo "youth_intake_academy_origin_mvp_test: OK\n";
