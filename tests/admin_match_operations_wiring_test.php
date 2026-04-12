<?php

$checks = [
    [
        'file' => __DIR__ . '/../app/Controllers/AdminMatchOperationsController.php',
        'needles' => [
            'class AdminMatchOperationsController extends Controller',
            'private AdminMatchOperationsService $service;',
            'public function index(): void',
            'public function repair(int $matchId): void',
            'public function rerun(int $matchId): void',
            'public function resetLineup(int $matchId): void',
            'public function syncCycle(): void',
            'requireAuth();',
            'requireAdmin();',
            '->repairLiveToScheduled(',
            '->rerunMatch(',
            '->resetLineupLock(',
            '->getCycleStates(',
            '->syncCycleState(',
        ],
    ],
    [
        'file' => __DIR__ . '/../public/index.php',
        'needles' => [
            "/admin/match-operations', 'AdminMatchOperationsController@index",
            "/admin/match-operations/{id}/repair', 'AdminMatchOperationsController@repair",
            "/admin/match-operations/{id}/rerun', 'AdminMatchOperationsController@rerun",
            "/admin/match-operations/{id}/reset-lineup', 'AdminMatchOperationsController@resetLineup",
            "/admin/match-operations/cycle/sync', 'AdminMatchOperationsController@syncCycle",
        ],
    ],
    [
        'file' => __DIR__ . '/../app/Views/admin/match-operations.php',
        'needles' => [
            'Match Filters',
            'name="status"',
            'name="competition_id"',
            'name="season_id"',
            'name="club_id"',
            'Repair LIVE',
            'Rerun',
            'Reset lineup lock',
            'Club Daily Cycle States',
            'Sync Cycle State',
        ],
    ],
];

$failed = false;
foreach ($checks as $check) {
    $content = file_get_contents($check['file']);
    foreach ($check['needles'] as $needle) {
        if (strpos($content, $needle) === false) {
            $failed = true;
            echo "Missing expected fragment in {$check['file']}: {$needle}\n";
        }
    }
}

if ($failed) {
    exit(1);
}

echo "admin_match_operations_wiring_test: OK\n";
