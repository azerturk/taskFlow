<?php

declare(strict_types=1);

require __DIR__.'/TestEnvironment.php';

TaskFlowTestEnvironment::configure('sqlite', [
    'DB_CONNECTION' => 'sqlite',
    'DB_DATABASE' => ':memory:',
    'DB_URL' => '',
]);

require dirname(__DIR__, 2).'/vendor/autoload.php';
