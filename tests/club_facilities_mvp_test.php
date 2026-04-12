<?php

$facilityService = file_get_contents(__DIR__ . '/../app/Services/ClubFacilityService.php');
$financeService = file_get_contents(__DIR__ . '/../app/Services/FinanceService.php');
$playerCareer = file_get_contents(__DIR__ . '/../app/Services/PlayerCareerService.php');
$dailyOrchestrator = file_get_contents(__DIR__ . '/../app/Services/DailyCycleOrchestrator.php');
$schema = file_get_contents(__DIR__ . '/../database/schema.sql');
$migration = file_get_contents(__DIR__ . '/../database/migrations/20260412_club_facilities_mvp.sql');

$facilityNeedles = [
    "'stadium'",
    "'training_ground'",
    "'youth_academy'",
    "'headquarters'",
    'public function upgradeFacility',
    'public function downgradeFacility',
    'public function postDailyMaintenance',
    'Only owner/admin can manage facilities.',
    'Facility is already at max level.',
    'Facility is already at minimum level.',
    'Insufficient balance for upgrade.',
    'uniq_club_facility_type',
];
foreach ($facilityNeedles as $needle) {
    if (strpos($facilityService, $needle) === false) {
        fwrite(STDERR, "Missing facility core fragment: {$needle}\n");
        exit(1);
    }
}

$financeNeedles = ['FACILITY_UPGRADE', 'FACILITY_DOWNGRADE_REFUND', 'FACILITY_MAINTENANCE'];
foreach ($financeNeedles as $needle) {
    if (strpos($financeService, $needle) === false) {
        fwrite(STDERR, "Missing finance routing fragment: {$needle}\n");
        exit(1);
    }
}

if (strpos($playerCareer, 'getReadinessRecoveryBonus') === false || strpos($playerCareer, 'getTrainingDevelopmentBonus') === false) {
    fwrite(STDERR, "Missing facility-effect wiring in PlayerCareerService\n");
    exit(1);
}

if (strpos($dailyOrchestrator, 'postDailyMaintenance') === false || strpos($dailyOrchestrator, 'facility_maintenance_postings') === false) {
    fwrite(STDERR, "Missing maintenance posting integration in DailyCycleOrchestrator\n");
    exit(1);
}

if (strpos($schema, 'CREATE TABLE IF NOT EXISTS club_facilities') === false) {
    fwrite(STDERR, "Missing club_facilities schema table\n");
    exit(1);
}

if (strpos($migration, 'CREATE TABLE IF NOT EXISTS club_facilities') === false) {
    fwrite(STDERR, "Missing club facilities migration table\n");
    exit(1);
}

echo "club_facilities_mvp_test: OK\n";
