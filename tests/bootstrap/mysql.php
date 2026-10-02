<?php

declare(strict_types=1);

require __DIR__.'/TestEnvironment.php';

$projectRoot = dirname(__DIR__, 2);

require $projectRoot.'/vendor/autoload.php';

Dotenv\Dotenv::createUnsafeImmutable($projectRoot, '.env.testing')->safeLoad();

$database = getenv('TASKFLOW_MYSQL_TEST_DATABASE');

if (! is_string($database) || preg_match('/^taskflow_test(?:_[a-z0-9_]+)?$/', $database) !== 1) {
    throw new RuntimeException(
        'MySQL compatibility tests require TASKFLOW_MYSQL_TEST_DATABASE to be a dedicated taskflow_test database.',
    );
}

$environment = [
    'DB_CONNECTION' => 'mysql',
    'DB_DATABASE' => $database,
];

foreach (['HOST', 'PORT', 'USERNAME', 'PASSWORD'] as $key) {
    $value = getenv("TASKFLOW_MYSQL_TEST_{$key}");

    if (is_string($value) && $value !== '') {
        $environment["DB_{$key}"] = $value;
    }
}

TaskFlowTestEnvironment::configure('mysql', $environment);
