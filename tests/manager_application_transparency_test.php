<?php

$checks = [
    [
        'file' => __DIR__ . '/../app/Models/ManagerApplicationModel.php',
        'needles' => [
            "status ENUM('pending','approved','rejected')",
            'rejection_reason',
            'reviewed_by_user_id',
            "LOWER(status) = 'pending'",
            'public function reject(int $applicationId, int $reviewerId, bool $isAdmin, string $reason)',
        ],
    ],
    [
        'file' => __DIR__ . '/../app/Controllers/ManagerHiringController.php',
        'needles' => [
            "'وارد کردن دلیل رد درخواست الزامی است.'",
            'reject($id, (int)Auth::id(), Auth::isAdmin(), $reason)',
            "'history' => \$this->applicationModel->getByCoach",
        ],
    ],
    [
        'file' => __DIR__ . '/../app/Views/manager/manage-applications.php',
        'needles' => [
            'Reason for rejection',
            'name="rejection_reason"',
            'required',
        ],
    ],
    [
        'file' => __DIR__ . '/../app/Views/manager/apply.php',
        'needles' => [
            'My Manager Applications',
            'Reason:',
            'status-chip',
        ],
    ],
];

$failed = false;

foreach ($checks as $check) {
    $content = file_get_contents($check['file']);
    foreach ($check['needles'] as $needle) {
        if (strpos($content, $needle) === false) {
            $failed = true;
            echo "Missing expected fragment in {$check['file']}: {$needle}\n";
        }
    }
}

if ($failed) {
    exit(1);
}

echo "manager_application_transparency_test: OK\n";
