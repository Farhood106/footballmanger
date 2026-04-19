<?php

$controller = file_get_contents(__DIR__ . '/../app/Controllers/SquadController.php');
$model = file_get_contents(__DIR__ . '/../app/Models/TacticModel.php');
$view = file_get_contents(__DIR__ . '/../app/Views/squad/tactics.php');

$needles = [
    [$controller, 'فرمیشن نامعتبر است'],
    [$controller, "'reload' => true"],
    [$controller, 'saveSetupAndLineup'],
    [$controller, 'getValidFormations'],
    [$model, "'4-1-4-1'"],
    [$model, "'4-1-2-1-2'"],
    [$model, "'4-5-1'"],
    [$model, "'4-4-1-1'"],
    [$model, "'5-3-2'"],
    [$view, 'id="formation-select"'],
    [$view, 'id="tactics-form"'],
    [$view, 'id="tactics-save-status"'],
    [$view, '/squad/tactics?formation='],
    [$view, 'فرمیشن فعال'],
    [$view, "other.value = ''"],
    [$view, '.lineup-slot-select'],
    [$view, 'new URLSearchParams(formData).toString()'],
    [$view, 'پاسخ نامعتبر از سرور دریافت شد'],
    [$view, 'اتصال با سرور برقرار نشد'],
];

foreach ($needles as [$haystack, $needle]) {
    if (strpos($haystack, $needle) === false) {
        fwrite(STDERR, "Missing tactics save/switch fragment: {$needle}\n");
        exit(1);
    }
}

echo "tactics_save_flow_and_formation_switch_mvp_test: OK\n";
