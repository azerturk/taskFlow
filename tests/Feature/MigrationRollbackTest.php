<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

test('the complete schema can roll back every loaded migration safely', function () {
    expect(Schema::hasTable('activity_log'))->toBeTrue()
        ->and(Schema::hasTable('project_members'))->toBeTrue()
        ->and(Schema::hasTable('task_attachments'))->toBeTrue();

    expect(Artisan::call('migrate:rollback', ['--force' => true]))->toBe(0)
        ->and(Schema::hasTable('activity_log'))->toBeFalse()
        ->and(Schema::hasTable('project_members'))->toBeFalse()
        ->and(Schema::hasTable('users'))->toBeFalse();
});
