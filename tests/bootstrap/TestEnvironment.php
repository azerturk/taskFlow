<?php

declare(strict_types=1);

final class TaskFlowTestEnvironment
{
    public static function temporaryRoot(string $profile): string
    {
        $repository = realpath(dirname(__DIR__, 2)) ?: dirname(__DIR__, 2);
        $root = rtrim(sys_get_temp_dir(), '\\/').'/taskflow-'.substr(hash('sha256', $repository), 0, 12).'-'.$profile;

        self::ensureDirectories([
            $root,
            $root.'/framework/cache',
            $root.'/framework/sessions',
            $root.'/framework/views',
            $root.'/logs',
        ]);

        return str_replace('\\', '/', $root);
    }

    /** @param array<string, string> $overrides */
    public static function configure(string $profile, array $overrides = []): string
    {
        $root = self::temporaryRoot($profile);
        $environment = array_replace([
            'APP_ENV' => 'testing',
            'APP_KEY' => 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=',
            'APP_MAINTENANCE_DRIVER' => 'file',
            'BCRYPT_ROUNDS' => '4',
            'BROADCAST_CONNECTION' => 'null',
            'CACHE_STORE' => 'array',
            'DB_FOREIGN_KEYS' => 'true',
            'FILESYSTEM_DISK' => 'local',
            'MAIL_MAILER' => 'array',
            'QUEUE_CONNECTION' => 'sync',
            'SESSION_DRIVER' => 'array',
            'LOG_CHANNEL' => 'errorlog',
            'PULSE_ENABLED' => 'false',
            'TELESCOPE_ENABLED' => 'false',
            'NIGHTWATCH_ENABLED' => 'false',
            'LARAVEL_STORAGE_PATH' => $root,
            'VIEW_COMPILED_PATH' => $root.'/framework/views',
            'APP_PACKAGES_CACHE' => $root.'/framework/cache/packages.php',
            'APP_SERVICES_CACHE' => $root.'/framework/cache/services.php',
            'APP_CONFIG_CACHE' => $root.'/framework/cache/config.php',
            'APP_ROUTES_CACHE' => $root.'/framework/cache/routes.php',
            'APP_EVENTS_CACHE' => $root.'/framework/cache/events.php',
        ], $overrides);

        foreach ($environment as $key => $value) {
            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }

        return $root;
    }

    /** @param list<string> $directories */
    private static function ensureDirectories(array $directories): void
    {
        foreach ($directories as $directory) {
            if (! is_dir($directory) && ! mkdir($directory, 0777, true) && ! is_dir($directory)) {
                throw new RuntimeException("Unable to create test directory: {$directory}");
            }
        }
    }
}
