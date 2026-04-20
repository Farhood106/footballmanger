<?php

$base = __DIR__ . '/../database/seed_sets/mini_v1';
$competitions = json_decode(file_get_contents($base . '/competitions.json'), true);
$clubs = json_decode(file_get_contents($base . '/clubs.json'), true);
$players = json_decode(file_get_contents($base . '/players.json'), true);
$readme = file_get_contents($base . '/README.md');

if (!is_array($competitions) || !is_array($clubs) || !is_array($players)) {
    fwrite(STDERR, "mini_seed_dataset_v1: decode error\n");
    exit(1);
}
if (count($competitions) !== 2) {
    fwrite(STDERR, "mini_seed_dataset_v1: expected 2 competitions\n");
    exit(1);
}
if (count($clubs) !== 8) {
    fwrite(STDERR, "mini_seed_dataset_v1: expected 8 clubs\n");
    exit(1);
}
if (count($players) !== 160) {
    fwrite(STDERR, "mini_seed_dataset_v1: expected 160 players\n");
    exit(1);
}

$compKeys = [];
foreach ($competitions as $c) {
    $k = (string)($c['external_key'] ?? '');
    if ($k === '' || isset($compKeys[$k])) {
        fwrite(STDERR, "mini_seed_dataset_v1: invalid/duplicate competition key\n");
        exit(1);
    }
    $compKeys[$k] = true;
}

$clubKeys = [];
foreach ($clubs as $club) {
    $k = (string)($club['external_key'] ?? '');
    if ($k === '' || isset($clubKeys[$k])) {
        fwrite(STDERR, "mini_seed_dataset_v1: invalid/duplicate club key\n");
        exit(1);
    }
    if (!isset($compKeys[(string)($club['competition_external_key'] ?? '')])) {
        fwrite(STDERR, "mini_seed_dataset_v1: club has unknown competition reference\n");
        exit(1);
    }
    $clubKeys[$k] = true;
}

$playerKeys = [];
$byClub = [];
$listed = 0;
$prospects = 0;
$older = 0;
foreach ($players as $p) {
    $k = (string)($p['external_key'] ?? '');
    if ($k === '' || isset($playerKeys[$k])) {
        fwrite(STDERR, "mini_seed_dataset_v1: invalid/duplicate player key\n");
        exit(1);
    }
    $playerKeys[$k] = true;
    $ck = (string)($p['club_external_key'] ?? '');
    if (!isset($clubKeys[$ck])) {
        fwrite(STDERR, "mini_seed_dataset_v1: player has unknown club reference\n");
        exit(1);
    }
    $byClub[$ck] = ($byClub[$ck] ?? 0) + 1;

    $age = (int) floor((time() - strtotime((string)$p['birth_date'])) / 31557600);
    if ($age <= 21) $prospects++;
    if ($age >= 32) $older++;

    if ((int)($p['is_transfer_listed'] ?? 0) === 1) {
        $listed++;
        if (empty($p['asking_price'])) {
            fwrite(STDERR, "mini_seed_dataset_v1: listed player missing asking price\n");
            exit(1);
        }
    }
}

foreach ($clubKeys as $clubKey => $_) {
    $count = (int)($byClub[$clubKey] ?? 0);
    if ($count < 18 || $count > 22) {
        fwrite(STDERR, "mini_seed_dataset_v1: club {$clubKey} has invalid squad size {$count}\n");
        exit(1);
    }
}

if ($listed < 6 || $prospects < 12 || $older < 8) {
    fwrite(STDERR, "mini_seed_dataset_v1: variation checks failed\n");
    exit(1);
}

if (strpos($readme, 'import_seed_set.php') === false) {
    fwrite(STDERR, "mini_seed_dataset_v1: README missing import usage\n");
    exit(1);
}

echo "mini_seed_dataset_v1_test: OK\n";
