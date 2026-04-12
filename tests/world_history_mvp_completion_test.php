<?php

$historyService = file_get_contents(__DIR__ . '/../app/Services/WorldHistoryService.php');
$competitionService = file_get_contents(__DIR__ . '/../app/Services/AdminCompetitionService.php');
$dashboardController = file_get_contents(__DIR__ . '/../app/Controllers/DashboardController.php');
$dashboardView = file_get_contents(__DIR__ . '/../app/Views/dashboard/index.php');
$historyController = file_get_contents(__DIR__ . '/../app/Controllers/ClubHistoryController.php');
$historyView = file_get_contents(__DIR__ . '/../app/Views/club/history.php');
$routes = file_get_contents(__DIR__ . '/../public/index.php');

$serviceNeedles = [
    'public function getRecentRecognitionsForClub',
    'public function getSeasonAwardsForClub',
    'public function getClubHonors',
    'public function getClubRecords',
    'public function getClubLegends',
    'uniq_award_scope',
    'uniq_honor',
    'uniq_club_record',
    'uniq_club_legend_player',
];
foreach ($serviceNeedles as $needle) {
    if (strpos($historyService, $needle) === false) {
        fwrite(STDERR, "Missing world history service fragment: {$needle}\n");
        exit(1);
    }
}

$triggerNeedles = [
    'applySeasonAwards($seasonId, (int)$season[\'competition_id\'])',
    'addClubHonor((int)$club[\'club_id\'], $seasonId, (int)$competition[\'id\'], \'PROMOTION\'',
    'addClubHonor((int)$club[\'club_id\'], $seasonId, (int)$competition[\'id\'], \'RELEGATION\'',
    'addClubHonor((int)$champion[\'club_id\'], $seasonId, (int)$competition[\'id\'], \'LEAGUE_TITLE\'',
    "'CHAMPIONS_QUALIFIED'",
];
foreach ($triggerNeedles as $needle) {
    if (strpos($competitionService, $needle) === false) {
        fwrite(STDERR, "Missing trigger integration fragment: {$needle}\n");
        exit(1);
    }
}

$controllerNeedles = [
    'class ClubHistoryController extends Controller',
    "'recent_recognitions' =>",
    "'season_awards' =>",
    "'honors' =>",
    "'records' =>",
    "'legends' =>",
];
foreach ($controllerNeedles as $needle) {
    if (strpos($historyController, $needle) === false) {
        fwrite(STDERR, "Missing history controller fragment: {$needle}\n");
        exit(1);
    }
}

if (strpos($routes, "/club/history', 'ClubHistoryController@index") === false) {
    fwrite(STDERR, "Missing /club/history route wiring\n");
    exit(1);
}

$dashboardNeedles = [
    'Club Awards & History',
    'Recent Recognitions',
    'recent_recognitions',
];
foreach ($dashboardNeedles as $needle) {
    if (strpos($dashboardController . $dashboardView, $needle) === false) {
        fwrite(STDERR, "Missing dashboard history visibility fragment: {$needle}\n");
        exit(1);
    }
}

$historyViewNeedles = [
    'Awards & History',
    'Recent Match Recognitions',
    'Season Awards',
    'Club Honors',
    'Club Records',
    'Club Legends',
];
foreach ($historyViewNeedles as $needle) {
    if (strpos($historyView, $needle) === false) {
        fwrite(STDERR, "Missing history view fragment: {$needle}\n");
        exit(1);
    }
}

echo "world_history_mvp_completion_test: OK\n";
