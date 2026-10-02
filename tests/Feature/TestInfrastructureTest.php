<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

test('the active test profile uses an isolated database and temporary local storage', function () {
    $driver = (string) config('database.default');

    expect($driver)->toBeIn(['sqlite', 'mysql'])
        ->and(config('cache.default'))->toBe('array')
        ->and(config('session.driver'))->toBe('array')
        ->and(config('mail.default'))->toBe('array')
        ->and(config('queue.default'))->toBe('sync');

    if ($driver === 'sqlite') {
        expect(config('database.connections.sqlite.database'))->toBe(':memory:');
    } else {
        expect((string) config('database.connections.mysql.database'))->toStartWith('taskflow_test');
    }

    Storage::disk('local')->put('test-bootstrap.txt', 'isolated');

    expect(Storage::disk('local')->get('test-bootstrap.txt'))->toBe('isolated');
});

test('the active test profile loads root and module migrations', function () {
    expect(DB::connection()->getDriverName())->toBeIn(['sqlite', 'mysql']);

    foreach (['users', 'projects', 'project_members', 'tasks', 'task_comments', 'task_attachments'] as $table) {
        expect(Schema::hasTable($table))->toBeTrue("Expected {$table} to be migrated for the default suite.");
    }
});
