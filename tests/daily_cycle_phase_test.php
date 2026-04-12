<?php
require_once __DIR__ . '/../app/Services/DailyCycleOrchestrator.php';

$cases = [
    ['2026-04-11 12:00:00', 'MATCH_1_LIVE'],
    ['2026-04-11 18:00:00', 'MATCH_2_LIVE'],
    ['2026-04-11 14:59:59', 'MATCH_1_LIVE'],
    ['2026-04-11 15:00:00', 'MATCH_2_LIVE'],
];

foreach ($cases as [$input, $expected]) {
    $actual = DailyCycleOrchestrator::phaseForMatchTime($input);
    if ($actual !== $expected) {
        fwrite(STDERR, "phaseForMatchTime failed for {$input}: expected {$expected}, got {$actual}\n");
        exit(1);
    }
}

echo "daily_cycle_phase_test: OK\n";
