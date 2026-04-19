<?php

$controller = file_get_contents(__DIR__ . '/../app/Controllers/SquadController.php');

$needles = [
    'requireClubForPage()',
    'requireClubIdForJson()',
    "redirect('/dashboard?error=' . urlencode('هیچ باشگاهی برای حساب شما تنظیم نشده است.'))",
    "\$this->json(['error' => 'هیچ باشگاهی برای حساب شما تنظیم نشده است.'], 400);",
    'resolveCurrentClub()',
    "Auth::isAdmin()",
];

foreach ($needles as $needle) {
    if (strpos($controller, $needle) === false) {
        fwrite(STDERR, "Missing null-club guard fragment: {$needle}\n");
        exit(1);
    }
}

echo "squad_null_club_guard_mvp_test: OK\n";
