<?php
require_once __DIR__ . '/../app/Services/AdminCompetitionService.php';

$rounds = AdminCompetitionService::buildRoundRobin([1,2,3,4]);
if (count($rounds) !== 6) {
    fwrite(STDERR, "Expected 6 rounds for 4 teams with double round robin\n");
    exit(1);
}

$matches = 0;
$pairSet = [];
foreach ($rounds as $round) {
    foreach ($round as [$h, $a]) {
        $matches++;
        $pairSet["{$h}-{$a}"] = true;
    }
}
if ($matches !== 12) {
    fwrite(STDERR, "Expected 12 matches for 4 teams double round robin\n");
    exit(1);
}

$serviceCode = file_get_contents(__DIR__ . '/../app/Services/AdminCompetitionService.php');
$mustContain = [
    'Another active season already exists for this competition.',
    'Fixtures already exist for this season.',
    "Cannot regenerate after live/finished matches.",
];
foreach ($mustContain as $needle) {
    if (strpos($serviceCode, $needle) === false) {
        fwrite(STDERR, "Missing season/fixture guard: {$needle}\n");
        exit(1);
    }
}

$controllerCode = file_get_contents(__DIR__ . '/../app/Controllers/AdminCompetitionController.php');
if (substr_count($controllerCode, 'requireAdmin();') < 5) {
    fwrite(STDERR, "Admin guard appears incomplete in AdminCompetitionController\n");
    exit(1);
}

echo "admin_competition_ops_test: OK\n";
