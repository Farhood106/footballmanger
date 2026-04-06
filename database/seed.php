<?php
/**
 * Database Seeder Entry Point
 * Run via: php database/seed.php
 */

define('ROOT', dirname(__DIR__));

// Load main config (not a separate database.php)
$config = require ROOT . '/config/config.php';

// Load seeders
require_once __DIR__ . '/seeds/SeederRunner.php';
require_once __DIR__ . '/seeds/LeagueSeeder.php';
require_once __DIR__ . '/seeds/ClubSeeder.php';
require_once __DIR__ . '/seeds/AbilitySeeder.php';
require_once __DIR__ . '/seeds/PlayerSeeder.php';

try {
    $db_cfg = $config['db'];
    $dsn    = "mysql:host={$db_cfg['host']};dbname={$db_cfg['name']};charset={$db_cfg['charset']}";
    $db     = new PDO($dsn, $db_cfg['user'], $db_cfg['pass'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $runner = new SeederRunner($db);
    $runner
        ->register('LeagueSeeder')
        ->register('ClubSeeder')
        ->register('AbilitySeeder')
        ->register('PlayerSeeder')
        ->run();

} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
    exit(1);
}
