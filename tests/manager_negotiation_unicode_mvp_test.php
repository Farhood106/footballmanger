<?php

$schema = file_get_contents(__DIR__ . '/../database/schema.sql');
$legacyMigration = file_get_contents(__DIR__ . '/../database/migrations/20260411_contract_negotiation_mvp.sql');
$unicodeFixMigration = file_get_contents(__DIR__ . '/../database/migrations/20260416_manager_negotiation_unicode_fix.sql');
$dbCore = file_get_contents(__DIR__ . '/../app/Core/Database.php');
$model = file_get_contents(__DIR__ . '/../app/Models/ManagerApplicationModel.php');

$needles = [
    [$schema, 'CREATE TABLE IF NOT EXISTS manager_contract_negotiations'],
    [$schema, 'DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'],
    [$legacyMigration, 'CREATE TABLE IF NOT EXISTS manager_contract_negotiations'],
    [$legacyMigration, 'DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'],
    [$unicodeFixMigration, 'ALTER TABLE manager_contract_negotiations'],
    [$unicodeFixMigration, 'CONVERT TO CHARACTER SET utf8mb4'],
    [$unicodeFixMigration, 'MODIFY COLUMN club_objective VARCHAR(255)'],
    [$unicodeFixMigration, 'CHARACTER SET utf8mb4'],
    [$dbCore, 'SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci'],
    [$model, "'club_objective' => trim(\$clubObjective)"],
];

foreach ($needles as [$haystack, $needle]) {
    if (strpos($haystack, $needle) === false) {
        fwrite(STDERR, "Missing manager negotiation unicode fragment: {$needle}\n");
        exit(1);
    }
}

echo "manager_negotiation_unicode_mvp_test: OK\n";
