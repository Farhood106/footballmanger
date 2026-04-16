<?php

$importer = file_get_contents(__DIR__ . '/../database/seeds/StructuredSeedImporter.php');
$miniCompetitions = file_get_contents(__DIR__ . '/../database/seed_sets/mini_v1/competitions.json');
$miniClubs = file_get_contents(__DIR__ . '/../database/seed_sets/mini_v1/clubs.json');
$miniPlayers = file_get_contents(__DIR__ . '/../database/seed_sets/mini_v1/players.json');

$needles = [
    [$importer, 'hydrateIdentityMaps'],
    [$importer, 'competitionCodeToId'],
    [$importer, 'clubCodeToId'],
    [$importer, 'nextSimulatedId'],
    [$importer, 'while (!empty($pending))'],
    [$importer, 'dataset_missing_or_unresolved_stage_map'],
    [$importer, 'dataset_missing_or_stage_resolution_failed'],
    [$importer, '$stage[\'inserted\']++'],
    [$importer, '$stage[\'updated\']++'],
    [$importer, '$this->dryRun'],

    [$miniCompetitions, 'IRN_PREM_V1'],
    [$miniCompetitions, 'IRN_DIV1_V1'],
    [$miniCompetitions, 'parent_external_key'],
    [$miniClubs, 'competition_external_key'],
    [$miniPlayers, 'club_external_key'],
];

foreach ($needles as [$haystack, $needle]) {
    if (strpos($haystack, $needle) === false) {
        fwrite(STDERR, "Missing staged-resolution fragment: {$needle}\n");
        exit(1);
    }
}

echo "seed_import_staged_resolution_mvp_test: OK\n";
