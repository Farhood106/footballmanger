<?php

$financeController = file_get_contents(__DIR__ . '/../app/Controllers/FinanceController.php');
$financeView = file_get_contents(__DIR__ . '/../app/Views/finance/index.php');
$dashboardController = file_get_contents(__DIR__ . '/../app/Controllers/DashboardController.php');
$dashboardView = file_get_contents(__DIR__ . '/../app/Views/dashboard/index.php');
$clubModel = file_get_contents(__DIR__ . '/../app/Models/ClubModel.php');
$routes = file_get_contents(__DIR__ . '/../public/index.php');

$needles = [
    [$financeController, 'public function updateSponsor(): void'],
    [$financeController, 'public function toggleSponsor(): void'],
    [$financeController, 'Brand name is required.'],
    [$financeController, 'sanitizeTier('],
    [$financeController, 'sanitizeUrl('],
    [$financeController, 'if (!$this->canManageClub($clubId))'],
    [$financeView, '/finance/sponsors/update'],
    [$financeView, '/finance/sponsors/toggle'],
    [$financeView, 'Deactivate'],
    [$financeView, 'Activate'],
    [$dashboardController, 'active_sponsors'],
    [$dashboardView, 'Club Sponsors'],
    [$dashboardView, 'Tier:'],
    [$clubModel, 'getSponsors(int $clubId, bool $activeOnly = false)'],
    [$routes, "/finance/sponsors/update', 'FinanceController@updateSponsor"],
    [$routes, "/finance/sponsors/toggle', 'FinanceController@toggleSponsor"],
];

foreach ($needles as [$haystack, $needle]) {
    if (strpos($haystack, $needle) === false) {
        fwrite(STDERR, "Missing sponsor management fragment: {$needle}\n");
        exit(1);
    }
}

echo "sponsor_management_mvp_test: OK\n";
