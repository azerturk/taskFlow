<?php

declare(strict_types=1);

require __DIR__.'/../bootstrap/TestEnvironment.php';

$root = TaskFlowTestEnvironment::temporaryRoot('e2e');
$database = $root.'/database.sqlite';

if (dirname($database) !== $root || basename($database) !== 'database.sqlite' || ! is_file($database)) {
    throw new RuntimeException('Run the guarded E2E setup before starting the test server.');
}

TaskFlowTestEnvironment::configure('e2e', [
    'DB_CONNECTION' => 'sqlite',
    'DB_DATABASE' => $database,
    'DB_URL' => '',
    'SESSION_DRIVER' => 'file',
    'SESSION_PATH' => $root.'/framework/sessions',
    'LOG_CHANNEL' => 'errorlog',
]);

$repository = dirname(__DIR__, 2);
$processEnvironment = getenv();
$processEnvironment = is_array($processEnvironment) ? array_replace($processEnvironment, $_ENV) : $_ENV;
$process = proc_open(
    [
        PHP_BINARY,
        '-S',
        '127.0.0.1:4173',
        $repository.'/vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php',
    ],
    [
        0 => ['file', 'php://stdin', 'r'],
        1 => ['file', 'php://stdout', 'w'],
        2 => ['file', 'php://stderr', 'w'],
    ],
    $pipes,
    $repository.'/public',
    $processEnvironment,
);

if (! is_resource($process)) {
    throw new RuntimeException('Unable to start the disposable E2E server.');
}

exit(proc_close($process));
