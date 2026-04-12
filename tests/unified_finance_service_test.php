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
    [$financeService, 'if ($manageTransaction && !$this->db->inTransaction())'],
    [$financeService, 'CONTRACT_SALARY_CYCLE'],
    [$financeService, 'postOwnerFunding'],
    [$financeService, 'Funding reference already posted.'],
    [$financeService, 'postSponsorIncome'],
    [$financeService, 'SPONSOR_INCOME_DAILY'],
    [$financeService, 'postSeasonReward'],
    [$financeService, "'SEASON_REWARD'"],
    [$financeService, "'reward_key' =>"],
    [$transferModel, 'new FinanceService'],
    [$transferModel, "'TRANSFER_OUT'"],
    [$governanceService, 'new FinanceService'],
    [$governanceService, 'GOVERNANCE_PENALTY'],
    [$governanceService, 'GOVERNANCE_COMPENSATION'],
    [$dailyOrchestrator, 'postCoachSalariesForCycle'],
    [$adminCompetitionService, 'postSeasonReward'],
    [$adminCompetitionService, "'PROMOTION'"],
    [$adminCompetitionService, "'TITLE'"],
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

$clubModel = file_get_contents(__DIR__ . '/../app/Models/ClubModel.php');
if (strpos($clubModel, 'UPDATE clubs SET balance = balance + ?') !== false) {
    fwrite(STDERR, "Direct balance mutation remains in ClubModel::updateBudget\n");
    exit(1);
}

$runtimeDirectBalanceHits = 0;
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__ . '/../app'));
foreach ($iterator as $fileInfo) {
    if (!$fileInfo->isFile() || $fileInfo->getExtension() !== 'php') {
        continue;
    }
    $file = $fileInfo->getPathname();
    $content = file_get_contents($file);
    if (strpos($content, 'UPDATE clubs SET balance = balance + ?') !== false && strpos($file, 'FinanceService.php') === false) {
        $runtimeDirectBalanceHits++;
    }
}
if ($runtimeDirectBalanceHits > 0) {
    fwrite(STDERR, "Unexpected direct runtime balance mutations detected outside FinanceService\n");
    exit(1);
}

echo "unified_finance_service_test: OK\n";
