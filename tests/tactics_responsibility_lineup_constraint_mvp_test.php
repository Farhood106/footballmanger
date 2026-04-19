<?php

$controller = file_get_contents(__DIR__ . '/../app/Controllers/SquadController.php');
$model = file_get_contents(__DIR__ . '/../app/Models/TacticModel.php');

$needles = [
    [$controller, '$lineupPlayerIds'],
    [$controller, "isset(\$lineupPlayerIds[\$captain])"],
    [$controller, "isset(\$lineupPlayerIds[\$penaltyTaker])"],
    [$controller, "isset(\$lineupPlayerIds[\$freekickTaker])"],
    [$controller, "isset(\$lineupPlayerIds[\$cornerTaker])"],
    [$controller, 'saveSetupAndLineup'],
    [$model, 'extractLineupPlayers'],
    [$model, 'normalizeResponsibilitiesForLineup'],
];

foreach ($needles as [$haystack, $needle]) {
    if (strpos($haystack, $needle) === false) {
        fwrite(STDERR, "Missing lineup-constrained responsibility fragment: {$needle}\n");
        exit(1);
    }
}

echo "tactics_responsibility_lineup_constraint_mvp_test: OK\n";
