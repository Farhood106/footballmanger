<?php

$controller = file_get_contents(__DIR__ . '/../app/Controllers/ManagerHiringController.php');
$model = file_get_contents(__DIR__ . '/../app/Models/ManagerApplicationModel.php');
$finance = file_get_contents(__DIR__ . '/../app/Services/FinanceService.php');
$manageView = file_get_contents(__DIR__ . '/../app/Views/manager/manage-applications.php');
$applyView = file_get_contents(__DIR__ . '/../app/Views/manager/apply.php');
$routes = file_get_contents(__DIR__ . '/../public/index.php');
$schema = file_get_contents(__DIR__ . '/../database/schema.sql');
$migration = file_get_contents(__DIR__ . '/../database/migrations/20260415_manager_contract_lifecycle_mvp.sql');
$schemaVerifier = file_get_contents(__DIR__ . '/../app/Core/SchemaSafetyVerifier.php');

$needles = [
    [$controller, 'public function terminateContract(): void'],
    [$model, 'terminateActiveContract'],
    [$model, 'MUTUAL_TERMINATION'],
    [$model, 'UPDATE clubs'],
    [$model, "manager_user_id = NULL, user_id = NULL"],
    [$model, "MANAGER_TERMINATION_COMPENSATION"],
    [$model, "manager_contract_terminations"],
    [$model, "No active manager contract found."],
    [$model, "UPDATE manager_contract_negotiations"],
    [$finance, "MANAGER_TERMINATION_COMPENSATION"],
    [$manageView, 'Active Manager Contracts'],
    [$manageView, 'termination_type'],
    [$applyView, 'Request Mutual Termination'],
    [$routes, "/manager/contracts/terminate', 'ManagerHiringController@terminateContract"],
    [$schema, 'CREATE TABLE IF NOT EXISTS manager_contract_terminations'],
    [$schema, 'MANAGER_TERMINATION_COMPENSATION'],
    [$migration, 'CREATE TABLE IF NOT EXISTS manager_contract_terminations'],
    [$migration, 'MANAGER_TERMINATION_COMPENSATION'],
    [$schemaVerifier, 'manager_contract_terminations'],
    [$schemaVerifier, 'MANAGER_TERMINATION_COMPENSATION'],
];

foreach ($needles as [$haystack, $needle]) {
    if (strpos($haystack, $needle) === false) {
        fwrite(STDERR, "Missing manager contract lifecycle fragment: {$needle}\n");
        exit(1);
    }
}

echo "contract_termination_lifecycle_mvp_test: OK\n";
