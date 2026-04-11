<?php

$financeService = file_get_contents(__DIR__ . '/../app/Services/FinanceService.php');
$transferModel = file_get_contents(__DIR__ . '/../app/Models/TransferModel.php');
$governanceService = file_get_contents(__DIR__ . '/../app/Services/GovernanceService.php');
$dailyOrchestrator = file_get_contents(__DIR__ . '/../app/Services/DailyCycleOrchestrator.php');
$adminCompetitionService = file_get_contents(__DIR__ . '/../app/Services/AdminCompetitionService.php');
$financeController = file_get_contents(__DIR__ . '/../app/Controllers/FinanceController.php');
$financeView = file_get_contents(__DIR__ . '/../app/Views/finance/index.php');
$routes = file_get_contents(__DIR__ . '/../public/index.php');
$schema = file_get_contents(__DIR__ . '/../database/schema.sql');
$migration = file_get_contents(__DIR__ . '/../database/migrations/20260411_unified_finance_mvp.sql');

$needles = [
    [$financeService, 'postEntry('],
    [$financeService, 'COACH_SALARY'],
    [$financeService, 'OWNER_FUNDING'],
    [$financeService, 'SPONSOR_INCOME'],
    [$financeService, 'MANUAL_ADMIN_ADJUSTMENT'],
    [$financeService, 'postCoachSalariesForCycle'],
    [$financeService, 'Duplicate finance posting blocked.'],
    [$financeService, 'CONTRACT_SALARY_CYCLE'],
    [$financeService, 'postOwnerFunding'],
    [$financeService, 'postSponsorIncome'],
    [$financeService, 'postSeasonReward'],
    [$transferModel, 'new FinanceService'],
    [$transferModel, "'TRANSFER_OUT'"],
    [$governanceService, 'new FinanceService'],
    [$governanceService, 'GOVERNANCE_PENALTY'],
    [$governanceService, 'GOVERNANCE_COMPENSATION'],
    [$dailyOrchestrator, 'postCoachSalariesForCycle'],
    [$adminCompetitionService, 'postSeasonReward'],
    [$financeController, 'ownerFunding'],
    [$financeController, 'addSponsor'],
    [$financeController, 'sponsorIncome'],
    [$financeView, 'Post Owner Funding'],
    [$financeView, 'Post Sponsor Income'],
    [$routes, "/finance', 'FinanceController@index"],
    [$routes, "/finance/owner-funding', 'FinanceController@ownerFunding"],
    [$schema, 'CREATE TABLE IF NOT EXISTS club_sponsors'],
    [$schema, 'CREATE TABLE IF NOT EXISTS club_owner_funding_events'],
    [$schema, 'COACH_SALARY'],
    [$migration, 'CREATE TABLE IF NOT EXISTS club_sponsors'],
    [$migration, 'CREATE TABLE IF NOT EXISTS club_owner_funding_events'],
    [$migration, 'ALTER TABLE club_finance_ledger MODIFY COLUMN entry_type ENUM'],
];

foreach ($needles as [$haystack, $needle]) {
    if (strpos($haystack, $needle) === false) {
        fwrite(STDERR, "Missing finance fragment: {$needle}\n");
        exit(1);
    }
}

echo "unified_finance_service_test: OK\n";
