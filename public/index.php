<?php
// public/index.php

session_start();

// Load configuration
require_once __DIR__ . '/../config/config.php';

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

// Squad management
$router->get('/squad', 'SquadController@index');
$router->get('/squad/tactics', 'SquadController@tactics');
$router->post('/squad/tactics/save', 'SquadController@saveTactic');
$router->get('/squad/player/{id}', 'SquadController@playerDetail');

// Matches
$router->get('/matches', 'MatchController@fixtures');
$router->get('/match/{id}', 'MatchController@detail');

// Transfers
$router->get('/transfers', 'TransferController@market');
$router->post('/transfer/bid', 'TransferController@makeBid');
$router->post('/transfer/accept/{id}', 'TransferController@acceptBid');
$router->post('/transfer/reject/{id}', 'TransferController@rejectBid');

// Competition
$router->get('/competition/{id}/standings', 'CompetitionController@standings');
$router->get('/competition/{id}/fixtures', 'CompetitionController@fixtures');

// Dispatch request
$router->dispatch();
