<?php

$controller = file_get_contents(__DIR__ . '/../app/Controllers/SquadController.php');
$model = file_get_contents(__DIR__ . '/../app/Models/TacticModel.php');
$schema = file_get_contents(__DIR__ . '/../database/schema.sql');
$migration = file_get_contents(__DIR__ . '/../database/migrations/20260419_tactics_mentality_alignment.sql');

$needles = [
    [$controller, 'getDefaultMentality()'],
    [$controller, 'normalizeMentality'],
    [$model, 'private const DEFAULT_MENTALITY = \'BALANCED\''],
    [$model, 'private const MENTALITIES = ['],
    [$model, "'ULTRA_ATTACK'"],
    [$model, "'ATTACK'"],
    [$model, "'BALANCED'"],
    [$model, "'DEFEND'"],
    [$model, "'ULTRA_DEFEND'"],
    [$model, "'AGGRESSIVE' => 'ATTACK'"],
    [$model, "'NORMAL' => 'BALANCED'"],
    [$model, "'CAUTIOUS' => 'DEFEND'"],
    [$schema, "mentality ENUM('ULTRA_ATTACK','ATTACK','BALANCED','DEFEND','ULTRA_DEFEND') DEFAULT 'BALANCED'"],
    [$migration, "ALTER TABLE tactics MODIFY COLUMN mentality ENUM('ULTRA_ATTACK','ATTACK','BALANCED','DEFEND','ULTRA_DEFEND') NOT NULL DEFAULT 'BALANCED'"],
];

foreach ($needles as [$haystack, $needle]) {
    if (strpos($haystack, $needle) === false) {
        fwrite(STDERR, "Missing mentality alignment fragment: {$needle}\n");
        exit(1);
    }
}

echo "tactics_mentality_alignment_mvp_test: OK\n";
