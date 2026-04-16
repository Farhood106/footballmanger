<?php

$routes = file_get_contents(__DIR__ . '/../public/index.php');
$controller = file_get_contents(__DIR__ . '/../app/Controllers/AdminController.php');
$view = file_get_contents(__DIR__ . '/../app/Views/admin/seed-import.php');
$adminIndex = file_get_contents(__DIR__ . '/../app/Views/admin/index.php');
$importer = file_get_contents(__DIR__ . '/../database/seeds/StructuredSeedImporter.php');

$needles = [
    [$routes, "/admin/seed', 'AdminController@seedImportPage"],
    [$routes, "/admin/seed/import', 'AdminController@importSeed"],

    [$controller, 'function seedImportPage'],
    [$controller, 'function importSeed'],
    [$controller, '$this->requireAdmin();'],
    [$controller, 'resolveSeedSetDirectory'],
    [$controller, 'listAvailableSeedSets'],
    [$controller, 'preg_match'],
    [$controller, 'database/seed_sets'],
    [$controller, 'StructuredSeedImporter'],
    [$controller, 'buildSeedImportPdo'],
    [$controller, 'wantsJson'],

    [$view, '/admin/seed/import'],
    [$view, 'dry_run'],
    [$view, 'Last import result'],

    [$adminIndex, '/admin/seed'],
    [$importer, 'class StructuredSeedImporter'],
];

foreach ($needles as [$haystack, $needle]) {
    if (strpos($haystack, $needle) === false) {
        fwrite(STDERR, "Missing admin seed import fragment: {$needle}\n");
        exit(1);
    }
}

echo "admin_seed_import_ui_endpoint_test: OK\n";
