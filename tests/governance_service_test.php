<?php
require_once __DIR__ . '/../app/Services/GovernanceService.php';

// case creation validation contract
$serviceCode = file_get_contents(__DIR__ . '/../app/Services/GovernanceService.php');
$requiredFragments = [
    "All case fields are required.",
    "Only current owner/manager can open a governance case.",
    "FOR UPDATE",
    "Case already closed.",
    "club_finance_ledger",
];

foreach ($requiredFragments as $fragment) {
    if (strpos($serviceCode, $fragment) === false) {
        fwrite(STDERR, "Missing governance fragment: {$fragment}\n");
        exit(1);
    }
}

// resolution status mapping
if (GovernanceService::finalStatusForDecision('CASE_REJECTED') !== 'rejected') {
    fwrite(STDERR, "Expected CASE_REJECTED => rejected\n");
    exit(1);
}
if (GovernanceService::finalStatusForDecision('PENALTY') !== 'resolved') {
    fwrite(STDERR, "Expected PENALTY => resolved\n");
    exit(1);
}

// finance effects mapping
$effects = GovernanceService::ledgerEffects(1000, 500);
if (count($effects) !== 2) {
    fwrite(STDERR, "Expected two ledger effects for penalty+compensation\n");
    exit(1);
}
if ($effects[0]['amount'] >= 0 || $effects[1]['amount'] >= 0) {
    fwrite(STDERR, "Expected negative ledger amounts for payouts\n");
    exit(1);
}

echo "governance_service_test: OK\n";
