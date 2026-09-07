<?php

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\LocalDemoUserSeeder;
use Database\Seeders\RolePermissionSeeder;
use Modules\Projects\Enums\ProjectMemberRole;
use Modules\Projects\Enums\ProjectStatus;
use Modules\Projects\Models\Project;
use Modules\Projects\Models\ProjectMember;
use Modules\Tasks\Enums\TaskPriority;
use Modules\Tasks\Enums\TaskStatus;
use Modules\Tasks\Models\Task;
use Modules\Tasks\Models\TaskAttachment;
use Modules\Tasks\Models\TaskComment;
use Spatie\Permission\Models\Role;

test('domain factories create persisted models with enum states and relations', function () {
    $project = Project::factory()->active()->create();
    $membership = ProjectMember::factory()->manager()->for($project)->create();
    $task = Task::factory()->assigned()->inProgress()->for($project)->create();
    $comment = TaskComment::factory()->for($task)->create();
    $attachment = TaskAttachment::factory()->for($task)->create();

    expect($project->status)->toBe(ProjectStatus::Active)
        ->and($membership->member_role)->toBe(ProjectMemberRole::Manager)
        ->and($task->priority)->toBe(TaskPriority::Medium)
        ->and($task->status)->toBe(TaskStatus::InProgress)
        ->and($task->assignee_id)->not->toBeNull()
        ->and($comment->task_id)->toBe($task->id)
        ->and($attachment->task_id)->toBe($task->id);
});

test('role factory helpers use canonical enum roles after role seeding', function () {
    $this->seed(RolePermissionSeeder::class);

    $admin = User::factory()->asAdmin()->create();
    $manager = User::factory()->asProjectManager()->create();
    $member = User::factory()->asMember()->create();

    expect($admin->hasRole(UserRole::Admin->value))->toBeTrue()
        ->and($manager->hasRole(UserRole::ProjectManager->value))->toBeTrue()
        ->and($member->hasRole(UserRole::Member->value))->toBeTrue();
});

test('database seeder creates canonical roles without unsafe demo accounts outside local', function () {
    $this->seed(DatabaseSeeder::class);

    expect(Role::query()->where('name', UserRole::Admin->value)->exists())->toBeTrue()
        ->and(Role::query()->where('name', UserRole::ProjectManager->value)->exists())->toBeTrue()
        ->and(Role::query()->where('name', UserRole::Member->value)->exists())->toBeTrue()
        ->and(User::query()->where('email', 'test@example.com')->exists())->toBeFalse()
        ->and(User::query()->where('email', 'admin@taskflow.test')->exists())->toBeFalse();
});

test('local demo seeder creates one usable account for every canonical role', function () {
    $this->seed(RolePermissionSeeder::class);
    $this->app['env'] = 'local';

    $this->seed(LocalDemoUserSeeder::class);

    expect(User::query()->where('email', 'admin@taskflow.test')->first()?->hasRole(UserRole::Admin->value))->toBeTrue()
        ->and(User::query()->where('email', 'manager@taskflow.test')->first()?->hasRole(UserRole::ProjectManager->value))->toBeTrue()
        ->and(User::query()->where('email', 'member@taskflow.test')->first()?->hasRole(UserRole::Member->value))->toBeTrue();
});
