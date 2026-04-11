<?php

$root = dirname(__DIR__);
$targets = [
    $root . '/app',
    $root . '/database/seeds',
];

$forbidden = [
    'overall_rating',
    'match_time',
    'competitions.league_id',
    'competitions.is_active',
    'players.name',
];

$violations = [];

$iter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($iter as $file) {
    $path = (string)$file;
    if (!preg_match('/\.php$/', $path)) {
        continue;
    }

    $inScope = false;
    foreach ($targets as $dir) {
        if (str_starts_with($path, $dir)) {
            $inScope = true;
            break;
        }
    }
    if (!$inScope) {
        continue;
    }

    $content = file_get_contents($path);
    foreach ($forbidden as $needle) {
        if (stripos($content, $needle) !== false) {
            $violations[] = [$path, $needle];
        }
    }
}

if ($violations) {
    foreach ($violations as [$path, $needle]) {
        echo "VIOLATION: {$needle} in {$path}\n";
    }
    exit(1);
}

echo "schema_reference_scan: OK\n";
