<?php
require_once __DIR__ . '/../app/Services/DailyCycleOrchestrator.php';

// 1) one-match club daily profile
if (DailyCycleOrchestrator::profileKey(1) !== 'one_match') {
    fwrite(STDERR, "Expected one_match profile for 1 match/day\n");
    exit(1);
}

// 2) two-match club daily profile
if (DailyCycleOrchestrator::profileKey(2) !== 'two_matches') {
    fwrite(STDERR, "Expected two_matches profile for 2 matches/day\n");
    exit(1);
}

// 3) invalid lineup rejection (duplicate + missing GK)
$bad = [
    ['player_id' => 1, 'position_slot' => 'CB', 'actual_position' => 'CB'],
    ['player_id' => 1, 'position_slot' => 'CB', 'actual_position' => 'CB'],
];
$validation = DailyCycleOrchestrator::validateLineupRows($bad);
if ($validation['ok'] !== false) {
    fwrite(STDERR, "Expected invalid lineup rejection for duplicate lineup\n");
    exit(1);
}

// 4) duplicate simulation prevention safeguard should exist in MatchEngine
$engine = file_get_contents(__DIR__ . '/../app/Services/MatchEngine.php');
$required = [
    'FOR UPDATE',
    "status = 'LIVE' WHERE id = ? AND status = 'SCHEDULED'",
    'private function claimScheduledMatch',
];
foreach ($required as $needle) {
    if (strpos($engine, $needle) === false) {
        fwrite(STDERR, "Missing duplicate-simulation safeguard fragment: {$needle}\n");
        exit(1);
    }
}

echo "gameplay_stabilization_test: OK\n";
