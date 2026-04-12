<?php

$model = file_get_contents(__DIR__ . '/../app/Models/ManagerApplicationModel.php');
$controller = file_get_contents(__DIR__ . '/../app/Controllers/ManagerHiringController.php');
$manageView = file_get_contents(__DIR__ . '/../app/Views/manager/manage-applications.php');
$applyView = file_get_contents(__DIR__ . '/../app/Views/manager/apply.php');
$routes = file_get_contents(__DIR__ . '/../public/index.php');
$schema = file_get_contents(__DIR__ . '/../database/schema.sql');
$migration = file_get_contents(__DIR__ . '/../database/migrations/20260411_contract_negotiation_mvp.sql');

$modelNeedles = [
    'CREATE TABLE IF NOT EXISTS manager_contract_negotiations',
    'public function sendOffer(',
    'public function respondToOffer(',
    "status = 'superseded'",
    "status = 'accepted'",
    'activateContractFromNegotiation',
    'Coach is already assigned as manager of another club.',
    'An active negotiation already exists for this application.',
    'Salary must be non-negative.',
    'Contract length must be a positive number of cycles.',
];
foreach ($modelNeedles as $needle) {
    if (strpos($model, $needle) === false) {
        fwrite(STDERR, "Missing negotiation model fragment: {$needle}\n");
        exit(1);
    }
}

$controllerNeedles = [
    'public function sendOffer(): void',
    'public function respondOfferAccept(int $id): void',
    'public function respondOfferReject(int $id): void',
    'public function respondOfferCounter(int $id): void',
    'respondToOffer(',
    'sendOffer(',
];
foreach ($controllerNeedles as $needle) {
    if (strpos($controller, $needle) === false) {
        fwrite(STDERR, "Missing negotiation controller fragment: {$needle}\n");
        exit(1);
    }
}

$routeNeedles = [
    "/manager/applications/offer', 'ManagerHiringController@sendOffer",
    "/manager/offers/{id}/accept', 'ManagerHiringController@respondOfferAccept",
    "/manager/offers/{id}/reject', 'ManagerHiringController@respondOfferReject",
    "/manager/offers/{id}/counter', 'ManagerHiringController@respondOfferCounter",
];
foreach ($routeNeedles as $needle) {
    if (strpos($routes, $needle) === false) {
        fwrite(STDERR, "Missing negotiation route fragment: {$needle}\n");
        exit(1);
    }
}

$viewNeedles = ['ارسال پیشنهاد قرارداد', 'Send Offer', 'Open Negotiations', 'Contract Offers', 'Send Counter Offer'];
foreach ($viewNeedles as $needle) {
    if (strpos($manageView . $applyView, $needle) === false) {
        fwrite(STDERR, "Missing negotiation UI fragment: {$needle}\n");
        exit(1);
    }
}

if (strpos($schema, 'CREATE TABLE IF NOT EXISTS manager_contract_negotiations') === false) {
    fwrite(STDERR, "Missing manager_contract_negotiations table in schema\n");
    exit(1);
}
if (strpos($migration, 'CREATE TABLE IF NOT EXISTS manager_contract_negotiations') === false) {
    fwrite(STDERR, "Missing negotiation migration table definition\n");
    exit(1);
}

echo "contract_negotiation_workflow_test: OK\n";
