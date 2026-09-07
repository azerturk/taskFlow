<?php

use App\Enums\AccountStatus;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Modules\Projects\Enums\ProjectMemberRole;
use Modules\Projects\Enums\ProjectStatus;
use Modules\Projects\Models\Project;
use Modules\Projects\Services\ProjectMemberService;
use Modules\Tasks\Data\CreateTaskData;
use Modules\Tasks\Enums\TaskPriority;
use Modules\Tasks\Models\Task;
use Modules\Tasks\Services\TaskAssignmentService;
use Modules\Tasks\Services\TaskService;
use Spatie\Activitylog\Models\Activity;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

function assignmentContext(): array
{
    $manager = User::factory()->asProjectManager()->create();
    $member = User::factory()->asMember()->create();
    $otherMember = User::factory()->asMember()->create();
    $outsider = User::factory()->asMember()->create();
    $project = Project::factory()->active()->create(['owner_id' => $manager->id, 'key' => 'ASN']);
    $members = app(ProjectMemberService::class);
    $members->addMember($project, $member, ProjectMemberRole::Member, actor: $manager);
    $members->addMember($project, $otherMember, ProjectMemberRole::Member, actor: $manager);
    $task = Task::factory()->for($project)->for($manager, 'creator')->create(['assignee_id' => null]);

    return [$manager, $member, $otherMember, $outsider, $project, $task];
}

test('a project member may self-assign through web and cannot assign another member', function (): void {
    [, $member, $otherMember, , , $task] = assignmentContext();

    $this->actingAs($member)
        ->patch(route('tasks.assign', $task), ['assignee_id' => $member->id])
        ->assertRedirect();

    expect($task->fresh()->assignee_id)->toBe($member->id);
    expect(DB::table('task_watchers')->where(['task_id' => $task->id, 'user_id' => $member->id])->exists())->toBeTrue()
        ->and(DB::table('notifications')->where('notifiable_id', $member->id)->count())->toBe(0);

    $this->actingAs($member)
        ->patch(route('tasks.assign', $task), ['assignee_id' => $otherMember->id])
        ->assertForbidden();
});

test('a manager can assign or unassign active project members through the API', function (): void {
    [$manager, $member, , , , $task] = assignmentContext();

    Sanctum::actingAs($manager, ['tasks:write']);
    $this->patchJson('/api/v1/tasks/'.$task->id.'/assignee', ['assignee_id' => $member->id])
        ->assertOk()
        ->assertJsonPath('data.assignee.id', $member->id);
    expect(DB::table('task_watchers')->where(['task_id' => $task->id, 'user_id' => $member->id])->exists())->toBeTrue()
        ->and(DB::table('notifications')->where('notifiable_id', $member->id)->count())->toBe(1);
    $this->patchJson('/api/v1/tasks/'.$task->id.'/assignee', ['assignee_id' => null])
        ->assertOk()
        ->assertJsonPath('data.assignee', null);
});

test('service rejects foreign removed suspended and read-only assignment targets', function (): void {
    [$manager, $member, $otherMember, $outsider, $project, $task] = assignmentContext();
    $service = app(TaskAssignmentService::class);

    expect(fn () => $service->assign($task->load('project'), $outsider, $manager))->toThrow(LogicException::class);

    app(ProjectMemberService::class)->removeMember($project, $otherMember, $manager);
    expect(fn () => $service->assign($task->fresh()->load('project'), $otherMember, $manager))->toThrow(LogicException::class);

    $member->forceFill(['status' => AccountStatus::Suspended])->save();
    expect(fn () => $service->assign($task->fresh()->load('project'), $member->fresh(), $manager))->toThrow(LogicException::class);

    $project->update(['status' => ProjectStatus::Completed]);
    expect(fn () => $service->assign($task->fresh()->load('project'), null, $manager))->toThrow(LogicException::class);
});

test('assignment no-op produces no duplicate activity and mutation records safe old and new values', function (): void {
    [$manager, $member, , , , $task] = assignmentContext();
    $service = app(TaskAssignmentService::class);

    $service->assign($task->load('project'), $member, $manager);
    $eventCount = Activity::query()->where('event', 'task.assigned')->count();
    $notificationCount = DB::table('notifications')->where('notifiable_id', $member->id)->count();
    $service->assign($task->fresh()->load('project'), $member, $manager);

    $properties = Activity::query()->where('event', 'task.assigned')->latest()->firstOrFail()->properties->toArray();
    expect(Activity::query()->where('event', 'task.assigned')->count())->toBe($eventCount)
        ->and(DB::table('notifications')->where('notifiable_id', $member->id)->count())->toBe($notificationCount)
        ->and($properties['old']['assignee_id'])->toBeNull()
        ->and($properties['new']['assignee_id'])->toBe($member->id)
        ->and($properties['new'])->toHaveKey('assignee_name');
});

test('member creation may self-assign but only a manager may assign another member', function (): void {
    [$manager, $member, $otherMember, , $project] = assignmentContext();
    $service = app(TaskService::class);

    expect($service->create($member, $project, new CreateTaskData('Self assigned report', null, $member->id, TaskPriority::Medium, null))->assignee_id)
        ->toBe($member->id)
        ->and($service->create($manager, $project, new CreateTaskData('Manager assigned report', null, $otherMember->id, TaskPriority::Medium, null))->assignee_id)
        ->toBe($otherMember->id)
        ->and(fn () => $service->create($member, $project, new CreateTaskData('Forbidden other assignment', null, $otherMember->id, TaskPriority::Medium, null)))->toThrow(LogicException::class);
});

test('initial and later assignment use the same single safe notification semantics', function (): void {
    [$manager, $member, , , $project] = assignmentContext();
    $created = app(TaskService::class)->create(
        $manager,
        $project,
        new CreateTaskData('Initially assigned', null, $member->id, TaskPriority::Medium, null),
    );
    $later = app(TaskService::class)->create(
        $manager,
        $project,
        new CreateTaskData('Assigned later', null, null, TaskPriority::Medium, null),
    );
    app(TaskAssignmentService::class)->assign($later, $member, $manager);

    $notifications = DB::table('notifications')->where('notifiable_id', $member->id)->orderBy('id')->get();
    $payloads = $notifications->map(fn (object $notification): array => json_decode($notification->data, true, flags: JSON_THROW_ON_ERROR));
    $firstKeys = array_keys($payloads[0]);
    $secondKeys = array_keys($payloads[1]);
    $taskIds = $payloads->pluck('task_id')->sort()->values()->all();
    $expectedTaskIds = [$created->id, $later->id];
    sort($expectedTaskIds);
    sort($firstKeys);
    sort($secondKeys);

    expect($notifications)->toHaveCount(2)
        ->and($payloads->pluck('event')->all())->toBe(['task.assigned', 'task.assigned'])
        ->and($taskIds)->toBe($expectedTaskIds)
        ->and($firstKeys)->toBe($secondKeys)
        ->and(DB::table('notifications')->where('notifiable_id', $manager->id)->count())->toBe(0);
});
