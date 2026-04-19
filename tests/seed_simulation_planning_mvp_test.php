<?php

$seedDoc = file_get_contents(__DIR__ . '/../docs/SEED_DATASET_AND_READINESS_AUDIT_MVP.md');
$simDoc = file_get_contents(__DIR__ . '/../docs/SIMULATION_VALIDATION_PLAN_MVP.md');
$templateReadme = file_get_contents(__DIR__ . '/../docs/examples/seed_templates/README.md');
$competitionsTemplate = file_get_contents(__DIR__ . '/../docs/examples/seed_templates/competitions.json');
$clubsTemplate = file_get_contents(__DIR__ . '/../docs/examples/seed_templates/clubs.json');
$playersTemplate = file_get_contents(__DIR__ . '/../docs/examples/seed_templates/players.json');

$needles = [
    [$seedDoc, 'Seed data category plan'],
    [$seedDoc, 'Competitions / divisions / leagues'],
    [$seedDoc, 'Seasons'],
    [$seedDoc, 'Clubs'],
    [$seedDoc, 'Players'],
    [$seedDoc, 'Contracts (manager domain)'],
    [$seedDoc, 'Club finances'],
    [$seedDoc, 'Facilities'],
    [$seedDoc, 'Sponsors'],
    [$seedDoc, 'Manager assignments'],
    [$seedDoc, 'Squad-role/readiness defaults'],
    [$seedDoc, 'Seed-readiness audit'],
    [$seedDoc, 'Good readiness areas'],
    [$seedDoc, 'Fragile / underdefined areas'],
    [$seedDoc, 'Missing/weak fields for realistic import'],
    [$seedDoc, 'Cleanup needed before large real-world dataset ingest'],
    [$seedDoc, 'Practical seed file structure recommendation'],

    [$simDoc, 'Scenario 1: One-season simulation baseline'],
    [$simDoc, 'Scenario 2: Multi-season continuity (3+ seasons)'],
    [$simDoc, 'Scenario 3: Transfer activity over time'],
    [$simDoc, 'Scenario 4: Youth intake emergence'],
    [$simDoc, 'Scenario 5: Financial pressure & recurring economy'],
    [$simDoc, 'Scenario 6: Promotion/relegation correctness'],
    [$simDoc, 'Scenario 7: Champions qualification correctness'],
    [$simDoc, 'Scenario 8: Manager vacancy/termination/replacement lifecycle'],
    [$simDoc, 'Scenario 9: Awards/history continuity'],
    [$simDoc, 'Bug/imbalance indicators'],

    [$templateReadme, 'shape-only examples'],
    [$competitionsTemplate, 'external_key'],
    [$competitionsTemplate, 'IRN_PREMIER'],
    [$clubsTemplate, 'competition_external_key'],
    [$playersTemplate, 'club_external_key'],
    [$playersTemplate, 'squad_role'],
];

foreach ($needles as [$haystack, $needle]) {
    if (strpos($haystack, $needle) === false) {
        fwrite(STDERR, "Missing seed/simulation planning fragment: {$needle}\n");
        exit(1);
    }
}

$comp = json_decode($competitionsTemplate, true);
$clubs = json_decode($clubsTemplate, true);
$players = json_decode($playersTemplate, true);
if (!is_array($comp) || !is_array($clubs) || !is_array($players)) {
    fwrite(STDERR, "Seed template JSON decode failed\n");
    exit(1);
}

echo "seed_simulation_planning_mvp_test: OK\n";
