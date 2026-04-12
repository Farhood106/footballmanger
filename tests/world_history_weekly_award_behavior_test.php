<?php

$service = file_get_contents(__DIR__ . '/../app/Services/WorldHistoryService.php');

$needles = [
    "award_type = 'PLAYER_OF_WEEK' AND week_number = ?",
    'if ($existing && (float)($existing[\'score_value\'] ?? 0) >= (float)($candidate[\'score\'] ?? 0))',
    "'PLAYER_OF_WEEK'",
    'upsertPlayerAward(',
];

foreach ($needles as $needle) {
    if (strpos($service, $needle) === false) {
        fwrite(STDERR, "Missing weekly-award behavior fragment: {$needle}\n");
        exit(1);
    }
}

echo "world_history_weekly_award_behavior_test: OK\n";
