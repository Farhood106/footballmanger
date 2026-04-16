<?php

$entrypoint = file_get_contents(__DIR__ . '/../database/import_seed_set.php');
$importer = file_get_contents(__DIR__ . '/../database/seeds/StructuredSeedImporter.php');
$schema = file_get_contents(__DIR__ . '/../database/schema.sql');
$migration = file_get_contents(__DIR__ . '/../database/migrations/20260415_seed_import_foundation_mvp.sql');
$schemaVerifier = file_get_contents(__DIR__ . '/../app/Core/SchemaSafetyVerifier.php');
$doc = file_get_contents(__DIR__ . '/../docs/SEED_IMPORT_FOUNDATION_MVP.md');

$needles = [
    [$entrypoint, 'import_seed_set.php'],
    [$entrypoint, "--path"],
    [$entrypoint, "--dry-run"],
    [$entrypoint, 'StructuredSeedImporter'],

    [$importer, 'class StructuredSeedImporter'],
    [$importer, 'importFromDirectory'],
    [$importer, 'importCompetitions'],
    [$importer, 'importClubs'],
    [$importer, 'importPlayers'],
    [$importer, 'validateCompetitions'],
    [$importer, 'validateClubs'],
    [$importer, 'validatePlayers'],
    [$importer, 'Duplicate competitions external_key'],
    [$importer, 'Duplicate clubs external_key'],
    [$importer, 'Duplicate players external_key'],
    [$importer, 'unknown parent_external_key'],
    [$importer, 'unknown competition_external_key'],
    [$importer, 'unknown club_external_key'],
    [$importer, 'supportsPlayerExternalKey'],

    [$schema, 'external_key VARCHAR(100) NULL'],
    [$schema, 'UNIQUE KEY uniq_player_external_key (external_key)'],
    [$migration, 'ADD COLUMN external_key VARCHAR(100) NULL'],
    [$migration, 'ADD UNIQUE KEY uniq_player_external_key (external_key)'],
    [$schemaVerifier, "'external_key'"],

    [$doc, 'Staged order'],
    [$doc, 'Non-destructive behavior'],
    [$doc, 'Validation behavior'],
    [$doc, 'Reporting'],
];

foreach ($needles as [$haystack, $needle]) {
    if (strpos($haystack, $needle) === false) {
        fwrite(STDERR, "Missing seed import foundation fragment: {$needle}\n");
        exit(1);
    }
}

echo "seed_import_foundation_mvp_test: OK\n";
