<?php
require_once __DIR__ . '/../app/Services/AIClubManagementService.php';

$cases = [
    [['owner_user_id' => 10, 'manager_user_id' => 10], 'OWNER_SELF_MANAGED'],
    [['owner_user_id' => 10, 'manager_user_id' => 20], 'HUMAN_OWNER_HUMAN_MANAGER'],
    [['owner_user_id' => 10, 'manager_user_id' => null], 'HUMAN_OWNER_CARETAKER'],
    [['owner_user_id' => null, 'manager_user_id' => 20], 'AI_OWNER_HUMAN_MANAGER'],
    [['owner_user_id' => null, 'manager_user_id' => null], 'AI_OWNER_CARETAKER'],
];

foreach ($cases as [$club, $expected]) {
    $state = AIClubManagementService::determineControlState($club);
    if (($state['key'] ?? '') !== $expected) {
        fwrite(STDERR, "Control state mismatch: expected {$expected}\n");
        exit(1);
    }
}

$serviceCode = file_get_contents(__DIR__ . '/../app/Services/AIClubManagementService.php');
$orchestratorCode = file_get_contents(__DIR__ . '/../app/Services/DailyCycleOrchestrator.php');
$adminController = file_get_contents(__DIR__ . '/../app/Controllers/AdminMatchOperationsController.php');
$adminView = file_get_contents(__DIR__ . '/../app/Views/admin/match-operations.php');

$serviceNeedles = [
    'ensureLineupForMatchPhase',
    'applyDailyPreparation',
    'Human-managed club lineup must not be overridden.',
    'AI manager could not build 11-player lineup.',
    'AI_OWNER_CARETAKER',
    'HUMAN_OWNER_CARETAKER',
];
foreach ($serviceNeedles as $needle) {
    if (strpos($serviceCode, $needle) === false) {
        fwrite(STDERR, "Missing AI service fragment: {$needle}\n");
        exit(1);
    }
}

$orchestratorNeedles = [
    'AIClubManagementService',
    'applyDailyPreparation',
    'ensureLineupForMatchPhase',
    "'ai_preparation'",
];
foreach ($orchestratorNeedles as $needle) {
    if (strpos($orchestratorCode, $needle) === false) {
        fwrite(STDERR, "Missing AI orchestrator integration fragment: {$needle}\n");
        exit(1);
    }
}

if (strpos($adminController, 'listClubControlStates') === false || strpos($adminView, 'Club Control States') === false) {
    fwrite(STDERR, "Missing admin AI control-state visibility wiring\n");
    exit(1);
}

echo "ai_club_management_test: OK\n";
