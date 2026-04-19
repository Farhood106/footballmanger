<?php
require_once __DIR__ . '/../app/Core/SchemaSafetyVerifier.php';

$publicIndex = file_get_contents(__DIR__ . '/../public/index.php');
$schedulerCli = file_get_contents(__DIR__ . '/../bin/run-daily-scheduler.php');

if (strpos($publicIndex, 'SchemaSafetyVerifier') === false || strpos($publicIndex, 'verifyOrFail') === false) {
    fwrite(STDERR, "public/index.php is missing startup schema verification hook\n");
    exit(1);
}

if (strpos($schedulerCli, 'SchemaSafetyVerifier') === false || strpos($schedulerCli, 'verifyOrFail') === false) {
    fwrite(STDERR, "bin/run-daily-scheduler.php is missing schema verification hook\n");
    exit(1);
}

$missing = [
    ['type' => 'table', 'name' => 'player_awards', 'detail' => 'missing'],
    ['type' => 'column', 'name' => 'players.fitness', 'detail' => 'missing'],
];

$strict = SchemaSafetyVerifier::buildDecision($missing, false);
if (($strict['ok'] ?? true) !== false || ($strict['level'] ?? '') !== 'error') {
    fwrite(STDERR, "Expected strict decision to fail when fallback OFF\n");
    exit(1);
}
if (strpos((string)$strict['message'], 'Startup aborted') === false) {
    fwrite(STDERR, "Expected strict error message to include Startup aborted\n");
    exit(1);
}

$compat = SchemaSafetyVerifier::buildDecision($missing, true);
if (($compat['ok'] ?? false) !== true || ($compat['level'] ?? '') !== 'warning') {
    fwrite(STDERR, "Expected compat decision to warn/continue when fallback ON\n");
    exit(1);
}
if (strpos((string)$compat['message'], 'compatibility mode') === false) {
    fwrite(STDERR, "Expected compatibility-mode warning text\n");
    exit(1);
}

$clean = SchemaSafetyVerifier::buildDecision([], false);
if (($clean['ok'] ?? false) !== true || ($clean['level'] ?? '') !== 'ok') {
    fwrite(STDERR, "Expected clean decision OK when no missing requirements\n");
    exit(1);
}

echo "schema_startup_safety_test: OK\n";
