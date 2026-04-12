<?php

$service = file_get_contents(__DIR__ . '/../app/Services/AdminCompetitionService.php');
$controller = file_get_contents(__DIR__ . '/../app/Controllers/AdminCompetitionController.php');
$view = file_get_contents(__DIR__ . '/../app/Views/admin/competitions.php');
$routes = file_get_contents(__DIR__ . '/../public/index.php');
$schema = file_get_contents(__DIR__ . '/../database/schema.sql');
$migration = file_get_contents(__DIR__ . '/../database/migrations/20260412_champions_qualification_mvp.sql');

$needles = [
    [$service, 'competition_qualification_slots'],
    [$service, 'saveQualificationSlot'],
    [$service, 'previewChampionsQualification'],
    [$service, 'applyChampionsQualification'],
    [$service, 'isSeasonCompletedForQualification'],
    [$service, 'Target season already has manual/non-qualification participants. Apply blocked for safety.'],
    [$service, '\'entry_type\' => ($rank === 0 ? \'champion\' : \'qualified\')'],
    [$controller, 'saveQualificationSlot(): void'],
    [$controller, 'previewQualifications(int $targetSeasonId): void'],
    [$controller, 'applyQualifications(int $targetSeasonId): void'],
    [$view, 'Champions Qualification Slots'],
    [$view, '/admin/qualifications/slots/save'],
    [$view, 'Preview Qualification'],
    [$view, 'Apply Qualification'],
    [$routes, "/admin/qualifications/slots/save', 'AdminCompetitionController@saveQualificationSlot"],
    [$routes, "/admin/seasons/{id}/qualifications/preview', 'AdminCompetitionController@previewQualifications"],
    [$routes, "/admin/seasons/{id}/qualifications/apply', 'AdminCompetitionController@applyQualifications"],
    [$schema, 'CREATE TABLE IF NOT EXISTS competition_qualification_slots'],
    [$migration, 'CREATE TABLE IF NOT EXISTS competition_qualification_slots'],
];

foreach ($needles as [$haystack, $needle]) {
    if (strpos($haystack, $needle) === false) {
        fwrite(STDERR, "Missing champions qualification fragment: {$needle}\n");
        exit(1);
    }
}

echo "champions_qualification_mvp_test: OK\n";
