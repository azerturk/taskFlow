<?php

use App\Enums\AccountStatus;
use App\Models\User;
use App\Repositories\Contracts\NotificationRepositoryInterface;
use App\Services\AdminUserService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\DB;
use Modules\Projects\Enums\ProjectMemberRole;
use Modules\Projects\Models\Project;
use Modules\Projects\Services\ProjectMemberService;
use Modules\Tasks\Enums\TaskStatus;
use Modules\Tasks\Models\Task;
use Modules\Tasks\Services\TaskWatcherNotificationService;
use Spatie\Activitylog\Models\Activity;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

function suspensionHistoryContext(): array
{
    $admin = User::factory()->asAdmin()->create();
    $target = User::factory()->asMember()->create();
    $watcher = User::factory()->asMember()->create();
    $project = Project::factory()->active()->create(['owner_id' => $admin->id]);
    $members = app(ProjectMemberService::class);
    $members->addMember($project, $target, ProjectMemberRole::Member, actor: $admin);
    $members->addMember($project, $watcher, ProjectMemberRole::Member, actor: $admin);
    $todo = Task::factory()->for($project)->for($admin, 'creator')->for($target, 'assignee')->create(['status' => TaskStatus::Todo, 'version' => 3]);
    $review = Task::factory()->for($project)->for($admin, 'creator')->for($target, 'assignee')->create(['status' => TaskStatus::Review, 'version' => 5]);
    $done = Task::factory()->for($project)->for($admin, 'creator')->for($target, 'assignee')->create(['status' => TaskStatus::Done, 'version' => 7]);
    foreach ([$todo, $review] as $task) {
        DB::table('task_watchers')->insert([
            ['task_id' => $task->id, 'user_id' => $target->id, 'created_at' => now(), 'updated_at' => now()],
            ['task_id' => $task->id, 'user_id' => $watcher->id, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    return compact('admin', 'target', 'watcher', 'project', 'todo', 'review', 'done');
}

test('suspension versions and audits each open assignment while preserving closed history', function (): void {
    extract(suspensionHistoryContext());
    $target->createToken('existing token');
    DB::table('sessions')->insert(['id' => 'target-session', 'user_id' => $target->id, 'ip_address' => '127.0.0.1', 'user_agent' => 'test', 'payload' => 'payload', 'last_activity' => now()->timestamp]);

    app(AdminUserService::class)->suspend($target, $admin);

    expect($target->fresh()->status)->toBe(AccountStatus::Suspended)
        ->and($todo->fresh()->assignee_id)->toBeNull()
        ->and($todo->fresh()->version)->toBe(4)
        ->and($review->fresh()->assignee_id)->toBeNull()
        ->and($review->fresh()->version)->toBe(6)
        ->and($done->fresh()->assignee_id)->toBe($target->id)
        ->and($done->fresh()->version)->toBe(7)
        ->and($todo->fresh()->creator_id)->toBe($admin->id)
        ->and(DB::table('task_watchers')->where('user_id', $target->id)->count())->toBe(0)
        ->and(DB::table('notifications')->where('notifiable_id', $target->id)->count())->toBe(0)
        ->and(DB::table('notifications')->where('notifiable_id', $watcher->id)->count())->toBe(2);

    $assignmentEvents = Activity::query()->where('event', 'task.assigned')->whereIn('subject_id', [$todo->id, $review->id])->orderBy('subject_id')->get();
    expect($assignmentEvents)->toHaveCount(2);
    foreach ($assignmentEvents as $event) {
        $properties = $event->properties->toArray();
        expect($properties['reason'])->toBe('assignee_suspended')
            ->and($properties['old']['assignee_id'])->toBe($target->id)
            ->and($properties['new']['assignee_id'])->toBeNull()
            ->and($properties['new']['version'])->toBe($properties['old']['version'] + 1);
    }

    $suspension = Activity::query()->where('event', 'user.suspended')->where('subject_id', $target->id)->sole();
    expect($suspension->properties->toArray())->toMatchArray([
        'unassigned_task_count' => 2,
        'watcher_subscription_count' => 2,
        'revoked_token_count' => 1,
        'revoked_session_count' => 1,
    ]);
});

test('an injected suspension notification failure rolls back every state and history mutation', function (): void {
    extract(suspensionHistoryContext());
    $token = $target->createToken('existing token');
    DB::table('sessions')->insert(['id' => 'target-session', 'user_id' => $target->id, 'ip_address' => '127.0.0.1', 'user_agent' => 'test', 'payload' => 'payload', 'last_activity' => now()->timestamp]);
    $notifications = Mockery::mock(NotificationRepositoryInterface::class);
    $notifications->shouldReceive('send')->once()->andThrow(new RuntimeException('injected notification failure'));
    app()->instance(NotificationRepositoryInterface::class, $notifications);
    app()->forgetInstance(TaskWatcherNotificationService::class);
    app()->forgetInstance(AdminUserService::class);

    expect(fn () => app(AdminUserService::class)->suspend($target, $admin))->toThrow(RuntimeException::class)
        ->and($target->fresh()->status)->toBe(AccountStatus::Active)
        ->and($todo->fresh()->assignee_id)->toBe($target->id)
        ->and($todo->fresh()->version)->toBe(3)
        ->and($review->fresh()->assignee_id)->toBe($target->id)
        ->and($review->fresh()->version)->toBe(5)
        ->and(DB::table('task_watchers')->where('user_id', $target->id)->count())->toBe(2)
        ->and(DB::table('personal_access_tokens')->where('id', $token->accessToken->id)->exists())->toBeTrue()
        ->and(DB::table('sessions')->where('id', 'target-session')->exists())->toBeTrue()
        ->and(Activity::query()->where('event', 'task.assigned')->whereIn('subject_id', [$todo->id, $review->id])->count())->toBe(0)
        ->and(Activity::query()->where('event', 'user.suspended')->where('subject_id', $target->id)->count())->toBe(0);
});
