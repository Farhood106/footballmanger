<?php

$controller = file_get_contents(__DIR__ . '/../app/Controllers/SquadController.php');
$model = file_get_contents(__DIR__ . '/../app/Models/TacticModel.php');
$view = file_get_contents(__DIR__ . '/../app/Views/squad/tactics.php');
$schema = file_get_contents(__DIR__ . '/../database/schema.sql');
$migration = file_get_contents(__DIR__ . '/../database/migrations/20260419_tactics_mentality_alignment.sql');

$needles = [
    [$controller, 'فرمیشن نامعتبر است'],
    [$controller, "'reload' => true"],
    [$controller, 'saveSetupAndLineup'],
    [$controller, 'normalizeMentality'],
    [$controller, 'getValidFormations'],
    [$controller, 'getValidMentalities'],
    [$controller, 'ذخیره تاکتیک ناموفق بود. لطفاً دوباره تلاش کنید.'],
    [$model, "'4-1-4-1'"],
    [$model, "'4-1-2-1-2'"],
    [$model, "'4-5-1'"],
    [$model, "'4-4-1-1'"],
    [$model, "'5-3-2'"],
    [$model, "'ULTRA_ATTACK'"],
    [$model, "'ULTRA_DEFEND'"],
    [$model, "'AGGRESSIVE' => 'ATTACK'"],
    [$model, "'CAUTIOUS' => 'DEFEND'"],
    [$view, 'id="formation-select"'],
    [$view, 'id="tactics-form"'],
    [$view, 'id="tactics-save-status"'],
    [$view, 'responsibility-select'],
    [$view, 'rebuildResponsibilitySelectors'],
    [$view, 'responsibilityPlayerPool'],
    [$view, '($mentalities ?? [])'],
    [$view, '/squad/tactics?formation='],
    [$view, 'فرمیشن فعال'],
    [$view, "other.value = ''"],
    [$view, '.lineup-slot-select'],
    [$view, 'new URLSearchParams(formData).toString()'],
    [$view, 'پاسخ نامعتبر از سرور دریافت شد'],
    [$view, 'اتصال با سرور برقرار نشد'],
    [$schema, "mentality ENUM('ULTRA_ATTACK','ATTACK','BALANCED','DEFEND','ULTRA_DEFEND') DEFAULT 'BALANCED'"],
    [$migration, "UPDATE tactics SET mentality = 'ATTACK' WHERE mentality = 'AGGRESSIVE'"],
    [$migration, "UPDATE tactics SET mentality = 'BALANCED' WHERE mentality = 'NORMAL'"],
    [$migration, "UPDATE tactics SET mentality = 'DEFEND' WHERE mentality = 'CAUTIOUS'"],
];

foreach ($needles as [$haystack, $needle]) {
    if (strpos($haystack, $needle) === false) {
        fwrite(STDERR, "Missing tactics save/switch fragment: {$needle}\n");
        exit(1);
    }
}

echo "tactics_save_flow_and_formation_switch_mvp_test: OK\n";
