<?php

$controller = file_get_contents(__DIR__ . '/../app/Controllers/SquadController.php');
$model = file_get_contents(__DIR__ . '/../app/Models/TacticModel.php');
$view = file_get_contents(__DIR__ . '/../app/Views/squad/tactics.php');
$footer = file_get_contents(__DIR__ . '/../app/Views/layout/footer.php');

$needles = [
    [$controller, 'فرمیشن نامعتبر است'],
    [$controller, "'reload' => true"],
    [$controller, 'getValidFormations'],
    [$model, "'4-1-4-1'"],
    [$model, "'4-1-2-1-2'"],
    [$model, "'4-5-1'"],
    [$model, "'4-4-1-1'"],
    [$model, "'5-3-2'"],
    [$view, 'id="formation-select"'],
    [$view, '/squad/tactics?formation='],
    [$view, 'فرمیشن فعال'],
    [$footer, 'application/x-www-form-urlencoded'],
    [$footer, 'new URLSearchParams(formData).toString()'],
];

$antiNeedles = [
    [$footer, 'JSON.stringify(data)'],
    [$footer, "'Content-Type': 'application/json'"],
];

foreach ($needles as [$haystack, $needle]) {
    if (strpos($haystack, $needle) === false) {
        fwrite(STDERR, "Missing tactics save/switch fragment: {$needle}\n");
        exit(1);
    }
}

foreach ($antiNeedles as [$haystack, $needle]) {
    if (strpos($haystack, $needle) !== false) {
        fwrite(STDERR, "Unexpected old ajax fragment still present: {$needle}\n");
        exit(1);
    }
}

echo "tactics_save_flow_and_formation_switch_mvp_test: OK\n";
