<?php

$model = file_get_contents(__DIR__ . '/../app/Models/TacticModel.php');
$controller = file_get_contents(__DIR__ . '/../app/Controllers/SquadController.php');
$view = file_get_contents(__DIR__ . '/../app/Views/squad/tactics.php');

$needles = [
    [$model, 'DELETE FROM tactic_lineups WHERE club_id = ? AND phase_key = ?'],
    [$model, 'saveSetupAndLineup'],
    [$controller, 'saveSetupAndLineup'],
    [$controller, "'reload' => true"],
    [$controller, 'ذخیره تاکتیک ناموفق بود. لطفاً دوباره تلاش کنید.'],
    [$view, 'پاسخ نامعتبر از سرور دریافت شد. لطفاً دوباره تلاش کنید.'],
];

$antiNeedles = [
    [$model, 'UPDATE tactic_lineups SET is_active = 0 WHERE club_id = ? AND phase_key = ?'],
];

foreach ($needles as [$haystack, $needle]) {
    if (strpos($haystack, $needle) === false) {
        fwrite(STDERR, "Missing formation-switch save fragment: {$needle}\n");
        exit(1);
    }
}

foreach ($antiNeedles as [$haystack, $needle]) {
    if (strpos($haystack, $needle) !== false) {
        fwrite(STDERR, "Unexpected old lineup-deactivation fragment present: {$needle}\n");
        exit(1);
    }
}

echo "tactics_formation_switch_save_stability_mvp_test: OK\n";
