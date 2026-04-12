<?php

$aiService = file_get_contents(__DIR__ . '/../app/Services/AIClubManagementService.php');
$orchestrator = file_get_contents(__DIR__ . '/../app/Services/DailyCycleOrchestrator.php');
$adminMatchView = file_get_contents(__DIR__ . '/../app/Views/admin/match-operations.php');
$schema = file_get_contents(__DIR__ . '/../database/schema.sql');
$migration = file_get_contents(__DIR__ . '/../database/migrations/20260412_ai_owner_caretaker_runtime_mvp.sql');

$needles = [
    [$aiService, 'HUMAN_OWNER_CARETAKER'],
    [$aiService, 'AI_OWNER_HUMAN_MANAGER'],
    [$aiService, 'AI_OWNER_CARETAKER'],
    [$aiService, 'syncVacancyStatesForAllClubs'],
    [$aiService, 'syncClubVacancyState'],
    [$aiService, 'club_control_runtime_states'],
    [$aiService, 'Human-managed club lineup must not be overridden.'],
    [$orchestrator, 'syncVacancyStatesForAllClubs'],
    [$orchestrator, "'vacancy_sync' =>"],
    [$adminMatchView, 'Owner Vacancy'],
    [$adminMatchView, 'Manager Vacancy'],
    [$adminMatchView, 'Caretaker'],
    [$schema, 'CREATE TABLE IF NOT EXISTS club_control_runtime_states'],
    [$migration, 'CREATE TABLE IF NOT EXISTS club_control_runtime_states'],
];

foreach ($needles as [$haystack, $needle]) {
    if (strpos($haystack, $needle) === false) {
        fwrite(STDERR, "Missing AI/caretaker vacancy fragment: {$needle}\n");
        exit(1);
    }
}

echo "ai_owner_caretaker_vacancy_test: OK\n";
