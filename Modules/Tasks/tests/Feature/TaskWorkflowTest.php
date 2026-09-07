<?php

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\Sanctum;
use Modules\Activity\Support\ActivityDisplay;
use Modules\Projects\Enums\ProjectMemberRole;
use Modules\Projects\Enums\ProjectStatus;
use Modules\Projects\Models\Project;
use Modules\Projects\Services\ProjectMemberService;
use Modules\Tasks\Data\ChangeTaskStatusData;
use Modules\Tasks\Enums\TaskStatus;
use Modules\Tasks\Exceptions\InvalidTaskStatusTransition;
use Modules\Tasks\Models\Task;
use Modules\Tasks\Services\TaskStatusService;
use Spatie\Activitylog\Models\Activity;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

function workflowContext(TaskStatus $status = TaskStatus::Backlog): array
{
    $manager = User::factory()->asProjectManager()->create();
    $assignee = User::factory()->asMember()->create();
    $outsider = User::factory()->asMember()->create();
    $project = Project::factory()->active()->create(['owner_id' => $manager->id, 'key' => 'FLW']);
    app(ProjectMemberService::class)->addMember($project, $assignee, ProjectMemberRole::Member, actor: $manager);
    $task = Task::factory()->for($project)->for($manager, 'creator')->create(['assignee_id' => $assignee->id, 'status' => $status]);

    return [$manager, $assignee, $outsider, $project, $task];
}

test('the full transition table gives assignees ordinary moves and managers terminal reopen authority', function (TaskStatus $from, array $expected, array $assigneeExpected): void {
    [$manager, $assignee, $outsider, , $task] = workflowContext($from);
    $service = app(TaskStatusService::class);

    expect(array_map(fn (TaskStatus $status): string => $status->value, $service->availableStatuses($task->load('project'), $manager)))->toBe($expected)
        ->and(array_map(fn (TaskStatus $status): string => $status->value, $service->availableStatuses($task->load('project'), $assignee)))->toBe($assigneeExpected)
        ->and($service->availableStatuses($task->load('project'), $outsider))->toBe([]);
})->with([
    'backlog' => [TaskStatus::Backlog, ['todo', 'cancelled'], ['todo', 'cancelled']],
    'todo' => [TaskStatus::Todo, ['backlog', 'in_progress', 'cancelled'], ['backlog', 'in_progress', 'cancelled']],
    'in progress' => [TaskStatus::InProgress, ['todo', 'review', 'cancelled'], ['todo', 'review', 'cancelled']],
    'review' => [TaskStatus::Review, ['in_progress', 'done', 'cancelled'], ['in_progress', 'done', 'cancelled']],
    'done' => [TaskStatus::Done, ['in_progress'], []],
    'cancelled' => [TaskStatus::Cancelled, ['backlog'], []],
]);

test('timestamps and parent completion guard follow the canonical workflow', function (): void {
    [$manager, $assignee, , , $task] = workflowContext();
    $service = app(TaskStatusService::class);
    $task = $service->change($task->load('project'), new ChangeTaskStatusData(TaskStatus::Todo, $task->version), $assignee);
    $task = $service->change($task->load('project'), new ChangeTaskStatusData(TaskStatus::InProgress, $task->version), $assignee);
    $startedAt = $task->started_at;
    $task = $service->change($task->load('project'), new ChangeTaskStatusData(TaskStatus::Todo, $task->version), $assignee);
    expect($task->started_at?->toISOString())->toBe($startedAt?->toISOString());

    $task = $service->change($task->load('project'), new ChangeTaskStatusData(TaskStatus::InProgress, $task->version), $assignee);
    $task = $service->change($task->load('project'), new ChangeTaskStatusData(TaskStatus::Review, $task->version), $assignee);
    $task = $service->change($task->load('project'), new ChangeTaskStatusData(TaskStatus::Done, $task->version), $assignee);
    expect($task->completed_at)->not->toBeNull();
    $task = $service->change($task->load('project'), new ChangeTaskStatusData(TaskStatus::InProgress, $task->version), $manager);
    expect($task->completed_at)->toBeNull()->and($task->started_at?->toISOString())->toBe($startedAt?->toISOString());
});

test('read-only projects and open subtasks are rejected by the service boundary', function (): void {
    [$manager, , , $project, $parent] = workflowContext(TaskStatus::Review);
    Task::factory()->for($project)->for($manager, 'creator')->create(['parent_id' => $parent->id, 'status' => TaskStatus::Todo]);
    $service = app(TaskStatusService::class);

    expect(fn () => $service->change($parent->load('project'), new ChangeTaskStatusData(TaskStatus::Done, $parent->version), $manager))->toThrow(InvalidTaskStatusTransition::class);

    $project->update(['status' => ProjectStatus::Archived]);
    expect(fn () => $service->change($parent->fresh()->load('project'), new ChangeTaskStatusData(TaskStatus::InProgress, $parent->version), $manager))->toThrow(InvalidTaskStatusTransition::class);
});

test('web and api status flows use the versioned service and stale API write returns one documented conflict', function (): void {
    [$manager, , , , $task] = workflowContext();

    $this->actingAs($manager)
        ->patch(route('tasks.status', $task), ['status' => TaskStatus::Todo->value, 'expected_version' => $task->version])
        ->assertRedirect();

    $task->refresh();
    Sanctum::actingAs($manager, ['tasks:write']);
    $this->patchJson('/api/v1/tasks/'.$task->id.'/status', ['status' => TaskStatus::InProgress->value, 'expected_version' => $task->version])
        ->assertOk()
        ->assertJsonPath('data.status', TaskStatus::InProgress->value)
        ->assertJsonPath('data.version', $task->version + 1);
    $activityCount = Activity::query()->where('event', 'task.status_changed')->count();
    $this->patchJson('/api/v1/tasks/'.$task->id.'/status', ['status' => TaskStatus::Review->value, 'expected_version' => $task->version])
        ->assertStatus(409)
        ->assertJsonPath('code', 'task_version_conflict');
    expect(Activity::query()->where('event', 'task.status_changed')->count())->toBe($activityCount);
});

test('status activity records one safe old and new status rank version tuple', function (): void {
    [$manager, $assignee, , $project, $task] = workflowContext();
    Task::factory()->for($project)->for($manager, 'creator')->create(['status' => TaskStatus::Todo, 'rank' => 4000]);

    $changed = app(TaskStatusService::class)->change(
        $task->load('project'),
        new ChangeTaskStatusData(TaskStatus::Todo, $task->version),
        $assignee,
    );
    $activities = Activity::query()->where('event', 'task.status_changed')->where('subject_id', $task->id)->get();

    expect($activities)->toHaveCount(1)
        ->and($activities->first()->properties->get('old'))->toMatchArray([
            'status' => TaskStatus::Backlog->value,
            'rank' => $task->rank,
            'version' => $task->version,
        ])
        ->and($activities->first()->properties->get('new'))->toMatchArray([
            'status' => TaskStatus::Todo->value,
            'rank' => 5000,
            'version' => $task->version + 1,
        ])
        ->and(ActivityDisplay::summary($activities->first()))->toBe('Backlog → Todo')
        ->and($changed->rank)->toBe(5000);
});
