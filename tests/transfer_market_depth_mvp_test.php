<?php

$transferController = file_get_contents(__DIR__ . '/../app/Controllers/TransferController.php');
$transferModel = file_get_contents(__DIR__ . '/../app/Models/TransferModel.php');
$transferView = file_get_contents(__DIR__ . '/../app/Views/transfer/market.php');
$aiService = file_get_contents(__DIR__ . '/../app/Services/AIClubManagementService.php');
$routes = file_get_contents(__DIR__ . '/../public/index.php');
$schema = file_get_contents(__DIR__ . '/../database/schema.sql');
$migration = file_get_contents(__DIR__ . '/../database/migrations/20260415_transfer_market_depth_mvp.sql');
$schemaVerifier = file_get_contents(__DIR__ . '/../app/Core/SchemaSafetyVerifier.php');

$needles = [
    [$transferController, 'public function counterBid(int $transferId): void'],
    [$transferController, 'canRespondToOffer'],
    [$transferController, "'COUNTERED'"],
    [$transferModel, "STATUS_COUNTERED"],
    [$transferModel, 'public function counter('],
    [$transferModel, 'buildPricingContext'],
    [$transferModel, 'getDispositionForPlayer'],
    [$transferModel, "status IN ('PENDING', 'COUNTERED')"],
    [$transferModel, "status = 'SUPERSEDED'"],
    [$transferModel, 'counter_fee'],
    [$transferView, '/transfer/counter/'],
    [$transferView, 'قبول کانتر'],
    [$routes, "/transfer/counter/{id}', 'TransferController@counterBid"],
    [$aiService, 'runDailyTransferMarket'],
    [$aiService, 'determineSellerDecision'],
    [$schema, "status ENUM('PENDING','COUNTERED','COMPLETED','CANCELLED','REJECTED','SUPERSEDED')"],
    [$schema, 'counter_fee BIGINT DEFAULT NULL'],
    [$schema, 'negotiation_round TINYINT DEFAULT 0'],
    [$migration, "ADD COLUMN counter_fee BIGINT"],
    [$migration, "ADD COLUMN negotiation_round TINYINT"],
    [$migration, "MODIFY COLUMN status ENUM('PENDING','COUNTERED','COMPLETED','CANCELLED','REJECTED','SUPERSEDED')"],
    [$schemaVerifier, "'counter_fee', 'negotiation_round', 'countered_at', 'responded_at'"],
];

foreach ($needles as [$haystack, $needle]) {
    if (strpos($haystack, $needle) === false) {
        fwrite(STDERR, "Missing transfer depth fragment: {$needle}\n");
        exit(1);
    }
}

echo "transfer_market_depth_mvp_test: OK\n";
