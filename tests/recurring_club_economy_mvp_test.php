<?php

$financeService = file_get_contents(__DIR__ . '/../app/Services/FinanceService.php');
$financeController = file_get_contents(__DIR__ . '/../app/Controllers/FinanceController.php');
$financeView = file_get_contents(__DIR__ . '/../app/Views/finance/index.php');
$dailyCycle = file_get_contents(__DIR__ . '/../app/Services/DailyCycleOrchestrator.php');
$schema = file_get_contents(__DIR__ . '/../database/schema.sql');
$migration = file_get_contents(__DIR__ . '/../database/migrations/20260412_recurring_club_economy_expansion_mvp.sql');

$serviceNeedles = [
    'PLAYER_WAGE',
    'OPERATING_COST',
    'postPlayerWagesForCycle',
    'postRecurringSponsorPayoutsForCycle',
    'postOperatingCostsForCycle',
    'PLAYER_WAGE_CYCLE',
    'SPONSOR_RECURRING_CYCLE',
    'OPERATING_COST_DAILY',
    'Duplicate finance posting blocked.',
    'canClubAffordExpense',
    'estimatePlayerWage',
    'defaultSponsorRecurringAmount',
];
foreach ($serviceNeedles as $needle) {
    if (strpos($financeService, $needle) === false) {
        fwrite(STDERR, "Missing recurring economy service fragment: {$needle}\n");
        exit(1);
    }
}

$runtimeNeedles = [
    'postPlayerWagesForCycle',
    'postRecurringSponsorPayoutsForCycle',
    'postOperatingCostsForCycle',
    'player_wage_postings',
    'sponsor_recurring_postings',
    'operating_cost_postings',
];
foreach ($runtimeNeedles as $needle) {
    if (strpos($dailyCycle, $needle) === false) {
        fwrite(STDERR, "Missing daily cycle recurring integration: {$needle}\n");
        exit(1);
    }
}

$controllerNeedles = ['getRecurringEconomySnapshot', 'recurring_summary', 'recurring_amount', 'recurring_cycle_days'];
foreach ($controllerNeedles as $needle) {
    if (strpos($financeController, $needle) === false) {
        fwrite(STDERR, "Missing finance controller recurring fragment: {$needle}\n");
        exit(1);
    }
}

$viewNeedles = ['Recurring Economy Snapshot', 'Player Wages', 'Operating Costs', 'recurring_amount', 'recurring_cycle_days'];
foreach ($viewNeedles as $needle) {
    if (strpos($financeView, $needle) === false) {
        fwrite(STDERR, "Missing finance view recurring fragment: {$needle}\n");
        exit(1);
    }
}

$schemaNeedles = [
    "'PLAYER_WAGE'",
    "'OPERATING_COST'",
    'recurring_amount BIGINT DEFAULT 0',
    'recurring_cycle_days INT DEFAULT 7',
    'last_paid_at DATETIME NULL',
];
foreach ($schemaNeedles as $needle) {
    if (strpos($schema, $needle) === false) {
        fwrite(STDERR, "Missing recurring economy schema fragment: {$needle}\n");
        exit(1);
    }
}

$migrationNeedles = ['ALTER TABLE club_sponsors ADD COLUMN recurring_amount', 'ALTER TABLE club_sponsors ADD COLUMN recurring_cycle_days', 'ALTER TABLE club_sponsors ADD COLUMN last_paid_at', "'PLAYER_WAGE'", "'OPERATING_COST'"];
foreach ($migrationNeedles as $needle) {
    if (strpos($migration, $needle) === false) {
        fwrite(STDERR, "Missing recurring economy migration fragment: {$needle}\n");
        exit(1);
    }
}

echo "recurring_club_economy_mvp_test: OK\n";
