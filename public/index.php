<?php
// public/index.php

session_start();

// Load configuration
$config = require __DIR__ . '/../config/config.php';

// Autoload classes
spl_autoload_register(function ($class) {
    $paths = [
        __DIR__ . '/../app/Core/' . $class . '.php',
        __DIR__ . '/../app/Controllers/' . $class . '.php',
        __DIR__ . '/../app/Models/' . $class . '.php',
        __DIR__ . '/../app/Services/' . $class . '.php',
    ];
    
    foreach ($paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            return;
        }
    }
});

// Startup schema verification (migration-first safety)
try {
    (new SchemaSafetyVerifier(Database::getInstance()))->verifyOrFail();
} catch (Throwable $e) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Startup schema verification failed.\n";
    echo $e->getMessage() . "\n";
    echo "Apply required migrations or enable temporary compatibility fallback with RUNTIME_DDL_FALLBACK=1.\n";
    exit;
}

// Initialize router
$router = new Router();

// Auth routes
$router->get('/login', 'AuthController@showLogin');
$router->post('/login', 'AuthController@login');
$router->get('/register', 'AuthController@showRegister');
$router->post('/register', 'AuthController@register');
$router->get('/logout', 'AuthController@logout');
$router->get('/club/select', 'AuthController@selectClub');
$router->post('/club/assign', 'AuthController@assignClub');

// Dashboard
$router->get('/', 'DashboardController@index');
$router->get('/dashboard', 'DashboardController@index');
$router->get('/club/history', 'ClubHistoryController@index');
$router->get('/club/facilities', 'ClubFacilitiesController@index');
$router->post('/club/facilities/upgrade', 'ClubFacilitiesController@upgrade');
$router->post('/club/facilities/downgrade', 'ClubFacilitiesController@downgrade');

// Squad management
$router->get('/squad', 'SquadController@index');
$router->get('/squad/tactics', 'SquadController@tactics');
$router->post('/squad/tactics/save', 'SquadController@saveTactic');
$router->post('/squad/role/save', 'SquadController@saveSquadRole');
$router->get('/squad/player/{id}', 'SquadController@playerDetail');

// Matches
$router->get('/matches', 'MatchController@fixtures');
$router->get('/match/{id}', 'MatchController@detail');

// Transfers
$router->get('/transfers', 'TransferController@market');
$router->post('/transfer/listing', 'TransferController@setListed');
$router->post('/transfer/bid', 'TransferController@makeBid');
$router->post('/transfer/counter/{id}', 'TransferController@counterBid');
$router->post('/transfer/accept/{id}', 'TransferController@acceptBid');
$router->post('/transfer/reject/{id}', 'TransferController@rejectBid');

// Competition
$router->get('/competition/{id}/standings', 'CompetitionController@standings');
$router->get('/competition/{id}/fixtures', 'CompetitionController@fixtures');

// Ownership requests
$router->get('/ownership/request', 'OwnershipController@requestForm');
$router->post('/ownership/request', 'OwnershipController@submitRequest');
$router->get('/ownership/manage', 'OwnershipController@manageRequests');
$router->post('/ownership/approve', 'OwnershipController@approveRequest');
$router->post('/ownership/reject', 'OwnershipController@rejectRequest');

// Manager hiring workflow
$router->get('/manager/expectations', 'ManagerHiringController@expectations');
$router->post('/manager/expectations/save', 'ManagerHiringController@saveExpectations');
$router->get('/manager/apply', 'ManagerHiringController@myApplications');
$router->post('/manager/apply/submit', 'ManagerHiringController@submitApplication');
$router->get('/manager/applications/manage', 'ManagerHiringController@manageApplications');
$router->post('/manager/applications/approve', 'ManagerHiringController@approveApplication');
$router->post('/manager/applications/reject', 'ManagerHiringController@rejectApplication');

$router->post('/manager/applications/offer', 'ManagerHiringController@sendOffer');
$router->post('/manager/offers/{id}/accept', 'ManagerHiringController@respondOfferAccept');
$router->post('/manager/offers/{id}/reject', 'ManagerHiringController@respondOfferReject');
$router->post('/manager/offers/{id}/counter', 'ManagerHiringController@respondOfferCounter');
$router->post('/manager/contracts/terminate', 'ManagerHiringController@terminateContract');



// Finance
$router->get('/finance', 'FinanceController@index');
$router->post('/finance/owner-funding', 'FinanceController@ownerFunding');
$router->post('/finance/sponsors/add', 'FinanceController@addSponsor');
$router->post('/finance/sponsors/update', 'FinanceController@updateSponsor');
$router->post('/finance/sponsors/toggle', 'FinanceController@toggleSponsor');
$router->post('/finance/sponsors/income', 'FinanceController@sponsorIncome');
$router->post('/finance/admin-adjust', 'FinanceController@manualAdjust');

// Governance
$router->get('/governance/cases', 'GovernanceController@index');
$router->get('/governance/cases/new', 'GovernanceController@createForm');
$router->post('/governance/cases/create', 'GovernanceController@createCase');
$router->get('/governance/cases/{id}', 'GovernanceController@detail');
$router->get('/governance/review', 'GovernanceController@reviewIndex');
$router->post('/governance/review/{id}/resolve', 'GovernanceController@resolve');

// Admin
$router->get('/admin', 'AdminController@index');
$router->get('/admin/seed', 'AdminController@seedImportPage');
$router->post('/admin/seed/import', 'AdminController@importSeed');
$router->get('/admin/clubs/create', 'AdminController@createClubForm');
$router->post('/admin/clubs/create', 'AdminController@storeClub');
$router->get('/admin/players/create', 'AdminController@createPlayerForm');
$router->post('/admin/players/create', 'AdminController@storePlayer');

$router->get('/admin/competitions', 'AdminCompetitionController@index');
$router->post('/admin/competitions/create', 'AdminCompetitionController@createCompetition');
$router->post('/admin/competitions/{id}/update', 'AdminCompetitionController@updateCompetition');
$router->post('/admin/competitions/{id}/toggle', 'AdminCompetitionController@toggleCompetition');
$router->post('/admin/seasons/create', 'AdminCompetitionController@createSeason');
$router->post('/admin/seasons/{id}/start', 'AdminCompetitionController@startSeason');
$router->post('/admin/seasons/{id}/end', 'AdminCompetitionController@endSeason');
$router->post('/admin/seasons/{id}/fixtures/generate', 'AdminCompetitionController@generateFixtures');
$router->post('/admin/seasons/{id}/finalize', 'AdminCompetitionController@finalizeSeason');
$router->post('/admin/seasons/{id}/rollover/apply', 'AdminCompetitionController@applyRollover');
$router->post('/admin/qualifications/slots/save', 'AdminCompetitionController@saveQualificationSlot');
$router->post('/admin/seasons/{id}/qualifications/preview', 'AdminCompetitionController@previewQualifications');
$router->post('/admin/seasons/{id}/qualifications/apply', 'AdminCompetitionController@applyQualifications');
$router->post('/admin/seasons/{season_id}/participants/add', 'AdminCompetitionController@addParticipant');
$router->post('/admin/seasons/{season_id}/participants/{club_id}/remove', 'AdminCompetitionController@removeParticipant');
$router->get('/admin/seasons/{id}/fixtures', 'AdminCompetitionController@fixtures');


$router->get('/admin/match-operations', 'AdminMatchOperationsController@index');
$router->post('/admin/match-operations/{id}/repair', 'AdminMatchOperationsController@repair');
$router->post('/admin/match-operations/{id}/rerun', 'AdminMatchOperationsController@rerun');
$router->post('/admin/match-operations/{id}/reset-lineup', 'AdminMatchOperationsController@resetLineup');
$router->post('/admin/match-operations/cycle/sync', 'AdminMatchOperationsController@syncCycle');

// Dispatch request
$router->dispatch();
