<?php

use App\Models\User;
use App\Notifications\TaskWatcherNotification;
use App\Services\AdminUserService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Modules\Projects\Enums\ProjectMemberRole;
use Modules\Projects\Models\Project;
use Modules\Projects\Services\ProjectMemberService;
use Modules\Tasks\Data\ChangeTaskStatusData;
use Modules\Tasks\Enums\TaskStatus;
use Modules\Tasks\Models\Task;
use Modules\Tasks\Services\TaskAssignmentService;
use Modules\Tasks\Services\TaskCommentService;
use Modules\Tasks\Services\TaskStatusService;
use Modules\Tasks\Services\TaskWatcherService;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->withoutVite();
});

function watcherContext(): array
{
    $manager = User::factory()->asProjectManager()->create();
    $reporter = User::factory()->asMember()->create();
    $watcher = User::factory()->asMember()->create();
    $outsider = User::factory()->asMember()->create();
    $project = Project::factory()->active()->create(['owner_id' => $manager->id]);
    $members = app(ProjectMemberService::class);
    $members->addMember($project, $reporter, ProjectMemberRole::Member, actor: $manager);
    $members->addMember($project, $watcher, ProjectMemberRole::Member, actor: $manager);
    $task = Task::factory()->for($project)->for($reporter, 'creator')->create(['assignee_id' => $reporter->id, 'status' => TaskStatus::Todo]);

    return [$manager, $reporter, $watcher, $outsider, $project, $task];
}

test('self-watch and manager watcher management enforce membership without granting task mutation authority', function (): void {
    [$manager, $reporter, $watcher, $outsider, $project, $task] = watcherContext();
    $this->actingAs($watcher)->post(route('tasks.watchers.store', $task))->assertRedirect();
    expect(DB::table('task_watchers')->where(['task_id' => $task->id, 'user_id' => $watcher->id])->exists())->toBeTrue()->and($watcher->can('update', $task))->toBeFalse();
    $this->actingAs($watcher)->post(route('tasks.watchers.store', $task), ['user_id' => $reporter->id])->assertForbidden();
    Sanctum::actingAs($manager, ['tasks:write']);
    $this->postJson('/api/v1/tasks/'.$task->id.'/watchers', ['user_id' => $reporter->id])->assertNoContent();
    $this->postJson('/api/v1/tasks/'.$task->id.'/watchers', ['user_id' => $outsider->id])->assertUnprocessable()->assertJsonValidationErrors('user_id');
    Sanctum::actingAs($outsider, ['tasks:read']);
    $this->getJson('/api/v1/tasks/'.$task->id.'/watchers')->assertNotFound();
});

test('watchers receive one notification per assignment comment and status action excluding actor', function (): void {
    [$manager, $reporter, $watcher, , , $task] = watcherContext();
    $watchers = app(TaskWatcherService::class);
    $watchers->watch($task->load('project'), $watcher, $watcher);
    $watchers->watch($task->fresh()->load('project'), $manager, $manager);
    app(TaskAssignmentService::class)->assign($task->fresh()->load(['project', 'assignee']), $watcher, $manager);
    expect(DB::table('notifications')->where('notifiable_id', $watcher->id)->count())->toBe(1)->and(DB::table('notifications')->where('notifiable_id', $manager->id)->count())->toBe(0);
    app(TaskCommentService::class)->create($task->fresh()->load('project'), $reporter, 'A comment');
    expect(DB::table('notifications')->where('notifiable_id', $watcher->id)->count())->toBe(2)->and(DB::table('notifications')->where('notifiable_id', $manager->id)->count())->toBe(1);
    $fresh = $task->fresh()->load('project');
    app(TaskStatusService::class)->change($fresh, new ChangeTaskStatusData(TaskStatus::InProgress, $fresh->version), $watcher);
    expect(DB::table('notifications')->where('notifiable_id', $watcher->id)->count())->toBe(2)->and(DB::table('notifications')->where('notifiable_id', $manager->id)->count())->toBe(2);
});

test('membership removal and suspension remove watcher subscriptions and notification access is user-scoped', function (): void {
    [$manager, $reporter, $watcher, , $project, $task] = watcherContext();
    $service = app(TaskWatcherService::class);
    $service->watch($task->load('project'), $watcher, $watcher);
    app(ProjectMemberService::class)->removeMember($project, $watcher, $manager);
    expect(DB::table('task_watchers')->where(['task_id' => $task->id, 'user_id' => $watcher->id])->exists())->toBeFalse();
    $service->watch($task->fresh()->load('project'), $reporter, $reporter);
    app(AdminUserService::class)->suspend($reporter, $manager);
    expect(DB::table('task_watchers')->where('user_id', $reporter->id)->exists())->toBeFalse();
    $manager->notify(new TaskWatcherNotification($task, $reporter, 'comment.created'));
    $this->actingAs($manager)->get(route('notifications.index'))->assertOk()->assertSee($task->number);
    $notificationId = DB::table('notifications')->where('notifiable_id', $manager->id)->value('id');
    $this->actingAs($watcher)->patch(route('notifications.read', $notificationId))->assertNotFound();
});
