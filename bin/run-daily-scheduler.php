<?php
// bin/run-daily-scheduler.php

$config = require __DIR__ . '/../config/config.php';

spl_autoload_register(function ($class) {
    $paths = [
        __DIR__ . '/../app/Core/' . $class . '.php',
        __DIR__ . '/../app/Controllers/' . $class . '.php',
        __DIR__ . '/../app/Models/' . $class . '.php',
        __DIR__ . '/../app/Services/' . $class . '.php',
    ];

    foreach ($paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            return;
        }
    }
});

try {
    (new SchemaSafetyVerifier(Database::getInstance()))->verifyOrFail();
} catch (Throwable $e) {
    fwrite(STDERR, "Startup schema verification failed.\n" . $e->getMessage() . "\n");
    fwrite(STDERR, "Apply required migrations or enable temporary compatibility fallback with RUNTIME_DDL_FALLBACK=1.\n");
    exit(1);
}

$scheduler = new DailySchedulerService();
$result = $scheduler->runDueMatches();

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
