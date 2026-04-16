<?php
/**
 * Structured Seed Import Entry Point
 * Usage:
 *   php database/import_seed_set.php --path=docs/examples/seed_templates
 *   php database/import_seed_set.php --path=docs/examples/seed_templates --dry-run=1
 */

define('ROOT', dirname(__DIR__));
$config = require ROOT . '/config/config.php';

require_once __DIR__ . '/seeds/StructuredSeedImporter.php';

$options = getopt('', ['path:', 'dry-run::']);
$path = (string)($options['path'] ?? (ROOT . '/docs/examples/seed_templates'));
$dryRunRaw = $options['dry-run'] ?? '0';
$dryRun = in_array((string)$dryRunRaw, ['1', 'true', 'yes'], true);

try {
    $dbCfg = $config['db'];
    $dsn = "mysql:host={$dbCfg['host']};dbname={$dbCfg['name']};charset={$dbCfg['charset']}";
    $db = new PDO($dsn, $dbCfg['user'], $dbCfg['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $importer = new StructuredSeedImporter($db, $dryRun);
    $report = $importer->importFromDirectory($path);

    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    exit(!empty($report['ok']) ? 0 : 1);
} catch (Throwable $e) {
    fwrite(STDERR, 'Seed import bootstrap error: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
