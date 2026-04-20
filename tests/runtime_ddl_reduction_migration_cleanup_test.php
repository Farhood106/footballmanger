<?php

$files = [
    __DIR__ . '/../app/Models/ManagerApplicationModel.php',
    __DIR__ . '/../app/Models/TransferModel.php',
    __DIR__ . '/../app/Services/FinanceService.php',
    __DIR__ . '/../app/Services/ClubFacilityService.php',
    __DIR__ . '/../app/Services/PlayerCareerService.php',
    __DIR__ . '/../app/Services/WorldHistoryService.php',
    __DIR__ . '/../app/Services/AIClubManagementService.php',
    __DIR__ . '/../app/Services/AdminCompetitionService.php',
];

foreach ($files as $file) {
    $content = file_get_contents($file);
    if (strpos($content, 'shouldRunRuntimeDdlFallback()') === false) {
        fwrite(STDERR, "Runtime DDL fallback gate missing in {$file}\n");
        exit(1);
    }
}

$dbCore = file_get_contents(__DIR__ . '/../app/Core/Database.php');
if (strpos($dbCore, 'function shouldRunRuntimeDdlFallback') === false) {
    fwrite(STDERR, "Database runtime DDL fallback switch missing\n");
    exit(1);
}

$newBackfill = file_get_contents(__DIR__ . '/../database/migrations/20260415_manager_application_schema_backfill.sql');
$needs = [
    'CREATE TABLE IF NOT EXISTS club_manager_expectations',
    'CREATE TABLE IF NOT EXISTS club_manager_applications',
    'ALTER TABLE club_manager_applications CHANGE COLUMN reviewed_by reviewed_by_user_id',
    'ALTER TABLE club_manager_applications ADD COLUMN rejection_reason',
];
foreach ($needs as $needle) {
    if (strpos($newBackfill, $needle) === false) {
        fwrite(STDERR, "Missing manager backfill migration fragment: {$needle}\n");
        exit(1);
    }
}

echo "runtime_ddl_reduction_migration_cleanup_test: OK\n";
