<?php

$transferController = file_get_contents(__DIR__ . '/../app/Controllers/TransferController.php');
$transferModel = file_get_contents(__DIR__ . '/../app/Models/TransferModel.php');
$playerModel = file_get_contents(__DIR__ . '/../app/Models/PlayerModel.php');
$transferView = file_get_contents(__DIR__ . '/../app/Views/transfer/market.php');
$routes = file_get_contents(__DIR__ . '/../public/index.php');
$schema = file_get_contents(__DIR__ . '/../database/schema.sql');
$migration = file_get_contents(__DIR__ . '/../database/migrations/20260412_transfer_market_core_expansion_mvp.sql');

$needles = [
    [$transferController, 'public function setListed(): void'],
    [$transferController, 'canManageClub'],
    [$transferController, 'پیشنهاد باید در بازه منطقی ارزش بازار باشد'],
    [$transferController, 'incoming_offers'],
    [$transferModel, 'getIncomingOffers'],
    [$transferModel, 'setTransferListed'],
    [$transferModel, "status = 'CANCELLED'"],
    [$transferModel, "status = 'PENDING'"],
    [$transferModel, 'FinanceService'],
    [$playerModel, 'p.is_transfer_listed = 1'],
    [$transferView, '/transfer/listing'],
    [$transferView, 'پیشنهادهای دریافتی'],
    [$transferView, '/transfer/accept/'],
    [$transferView, '/transfer/reject/'],
    [$routes, "/transfer/listing', 'TransferController@setListed"],
    [$schema, 'is_transfer_listed BOOLEAN DEFAULT 0'],
    [$schema, 'asking_price BIGINT DEFAULT NULL'],
    [$schema, 'transfer_listed_at DATETIME NULL'],
    [$schema, 'season_id INT'],
    [$migration, 'ALTER TABLE players ADD COLUMN is_transfer_listed'],
    [$migration, 'ALTER TABLE transfers ADD COLUMN season_id'],
];

foreach ($needles as [$haystack, $needle]) {
    if (strpos($haystack, $needle) === false) {
        fwrite(STDERR, "Missing transfer core fragment: {$needle}\n");
        exit(1);
    }
}

echo "transfer_market_core_mvp_test: OK\n";
