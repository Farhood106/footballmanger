<?php

$controller = file_get_contents(__DIR__ . '/../app/Controllers/ClubFacilitiesController.php');
$view = file_get_contents(__DIR__ . '/../app/Views/club/facilities.php');
$routes = file_get_contents(__DIR__ . '/../public/index.php');
$dashboardController = file_get_contents(__DIR__ . '/../app/Controllers/DashboardController.php');
$dashboardView = file_get_contents(__DIR__ . '/../app/Views/dashboard/index.php');

$controllerNeedles = [
    'class ClubFacilitiesController extends Controller',
    'public function index(): void',
    'public function upgrade(): void',
    'public function downgrade(): void',
    'ClubFacilityService',
];
foreach ($controllerNeedles as $needle) {
    if (strpos($controller, $needle) === false) {
        fwrite(STDERR, "Missing facilities controller fragment: {$needle}\n");
        exit(1);
    }
}

$routeNeedles = [
    "/club/facilities', 'ClubFacilitiesController@index",
    "/club/facilities/upgrade', 'ClubFacilitiesController@upgrade",
    "/club/facilities/downgrade', 'ClubFacilitiesController@downgrade",
];
foreach ($routeNeedles as $needle) {
    if (strpos($routes, $needle) === false) {
        fwrite(STDERR, "Missing facilities route: {$needle}\n");
        exit(1);
    }
}

$viewNeedles = [
    'Club Facilities',
    'Upgrade Cost',
    'Downgrade Refund',
    'Daily Maintenance',
    'Image Ref',
    '/club/facilities/upgrade',
    '/club/facilities/downgrade',
];
foreach ($viewNeedles as $needle) {
    if (strpos($view, $needle) === false) {
        fwrite(STDERR, "Missing facilities view fragment: {$needle}\n");
        exit(1);
    }
}

if (strpos($dashboardController, 'facility_overview') === false || strpos($dashboardView, 'Facility Overview') === false || strpos($dashboardView, '/club/facilities') === false) {
    fwrite(STDERR, "Missing dashboard facilities visibility integration\n");
    exit(1);
}

echo "club_facilities_ui_wiring_test: OK\n";
