<?php

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\Sanctum;
use Modules\Activity\Support\ActivityDisplay;
use Modules\Projects\Enums\ProjectMemberRole;
use Modules\Projects\Models\Project;
use Modules\Projects\Services\ProjectMemberService;
use Modules\Tasks\Data\ReorderTaskData;
use Modules\Tasks\Enums\TaskStatus;
use Modules\Tasks\Exceptions\InvalidTaskRankPosition;
use Modules\Tasks\Exceptions\TaskVersionConflict;
use Modules\Tasks\Models\Task;
use Modules\Tasks\Services\TaskRankService;
use Spatie\Activitylog\Models\Activity;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

function rankContext(): array
{
    $manager = User::factory()->asProjectManager()->create();
    $member = User::factory()->asMember()->create();
    $project = Project::factory()->active()->create(['owner_id' => $manager->id]);
    $other = Project::factory()->active()->create(['owner_id' => $manager->id]);
    app(ProjectMemberService::class)->addMember($project, $member, ProjectMemberRole::Member, actor: $manager);
    $a = Task::factory()->for($project)->for($manager, 'creator')->create(['status' => TaskStatus::Backlog, 'rank' => 1000]);
    $b = Task::factory()->for($project)->for($manager, 'creator')->create(['status' => TaskStatus::Backlog, 'rank' => 2000]);
    $c = Task::factory()->for($project)->for($manager, 'creator')->create(['status' => TaskStatus::Backlog, 'rank' => 3000, 'assignee_id' => $member->id]);

    return [$manager, $member, $project, $other, $a, $b, $c];
}

test('manager neighbor reorder follows before and after semantics with collision-safe deterministic ranks', function (): void {
    [$manager, , $project, , $a, $b, $c] = rankContext();
    $service = app(TaskRankService::class);
    $moved = $service->reorder($c->load('project'), new ReorderTaskData($a->id, $b->id, $c->version), $manager);

    expect(Task::query()->where('project_id', $project->id)->orderBy('rank')->pluck('id')->all())
        ->toBe([$a->id, $c->id, $b->id])
        ->and(Task::query()->where('project_id', $project->id)->orderBy('rank')->pluck('rank')->all())
        ->toBe([1000, 2000, 3000])
        ->and($moved->version)->toBe($c->version + 1);

    $activity = Activity::query()->where('event', 'task.reordered')->where('subject_id', $c->id)->latest('id')->firstOrFail();
    expect($activity->properties->get('old'))->toBe(['rank' => 3000, 'version' => $c->version])
        ->and($activity->properties->get('new'))->toBe(['rank' => 2000, 'version' => $c->version + 1])
        ->and(ActivityDisplay::summary($activity))->toBe('Position 2000');

    expect(fn () => $service->reorder($moved->load('project'), new ReorderTaskData(null, null, $c->version), $manager))
        ->toThrow(TaskVersionConflict::class);
});

test('no-op reorder preserves version and emits no activity', function (): void {
    [$manager, , , , , , $c] = rankContext();
    $before = Activity::query()->where('event', 'task.reordered')->count();
    $result = app(TaskRankService::class)->reorder(
        $c->load('project'),
        new ReorderTaskData(null, null, $c->version),
        $manager,
    );

    expect($result->rank)->toBe(3000)
        ->and($result->version)->toBe($c->version)
        ->and(Activity::query()->where('event', 'task.reordered')->count())->toBe($before);
});

test('rebalance preserves uniqueness when a soft-deleted task reserves a rank', function (): void {
    [$manager, , $project, , $a, $b, $c] = rankContext();
    $a->delete();

    $moved = app(TaskRankService::class)->reorder(
        $c->load('project'),
        new ReorderTaskData(null, $b->id, $c->version),
        $manager,
    );

    expect(Task::query()->where('project_id', $project->id)->orderBy('rank')->pluck('id')->all())
        ->toBe([$c->id, $b->id])
        ->and(Task::withTrashed()->where('project_id', $project->id)->pluck('rank')->unique()->count())->toBe(3)
        ->and($moved->rank)->toBe(2000);
});

test('cross-context non-adjacent and non-manager reorder are rejected while status move appends', function (): void {
    [$manager, $member, $project, $other, $a, $b, $c] = rankContext();
    $foreign = Task::factory()->for($other)->for($manager, 'creator')->create(['status' => TaskStatus::Backlog, 'rank' => 1000]);
    $target = Task::factory()->for($project)->for($manager, 'creator')->create(['status' => TaskStatus::Todo, 'rank' => 1000]);
    $service = app(TaskRankService::class);

    expect(fn () => $service->reorder($a->load('project'), new ReorderTaskData($foreign->id, null, $a->version), $manager))
        ->toThrow(InvalidTaskRankPosition::class)
        ->and(fn () => $service->reorder($a->load('project'), new ReorderTaskData($c->id, $b->id, $a->version), $manager))
        ->toThrow(InvalidTaskRankPosition::class)
        ->and(fn () => $service->reorder($a->load('project'), new ReorderTaskData(null, null, $a->version), $member))
        ->toThrow(LogicException::class);

    $this->actingAs($member)->patch(route('tasks.status', $c), ['status' => 'todo', 'expected_version' => $c->version])->assertRedirect();
    expect($c->fresh()->rank)->toBe($target->rank + 1000);
});

test('API reorder returns canonical conflict codes and backlog excludes other statuses', function (): void {
    [$manager, , $project, $other, $a, $b, $c] = rankContext();
    $foreign = Task::factory()->for($other)->for($manager, 'creator')->create(['status' => TaskStatus::Backlog, 'rank' => 1000]);
    $todo = Task::factory()->for($project)->for($manager, 'creator')->create(['status' => TaskStatus::Todo, 'rank' => 1000]);
    Sanctum::actingAs($manager, ['tasks:read', 'tasks:write']);

    $this->patchJson('/api/v1/tasks/'.$c->id.'/rank', [
        'before_task_id' => $a->id,
        'after_task_id' => $b->id,
        'expected_version' => $c->version,
    ])->assertOk()->assertJsonPath('data.rank', 2000);

    $this->patchJson('/api/v1/tasks/'.$c->id.'/rank', [
        'after_task_id' => $foreign->id,
        'expected_version' => $c->version + 1,
    ])->assertStatus(409)->assertJsonPath('code', 'invalid_task_rank_position');

    $this->patchJson('/api/v1/tasks/'.$c->id.'/rank', [
        'after_task_id' => 999999,
        'expected_version' => $c->version + 1,
    ])->assertStatus(409)->assertJsonPath('code', 'invalid_task_rank_position');

    $this->getJson('/api/v1/projects/'.$project->id.'/backlog?per_page=2')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonMissing(['id' => $todo->id]);
});

test('task creation allocates a non-zero unique backlog rank before the first insert', function (): void {
    $manager = User::factory()->asProjectManager()->create();
    $project = Project::factory()->active()->create(['owner_id' => $manager->id, 'key' => 'RNK']);
    $payload = ['type' => 'task', 'title' => 'Atomic rank', 'priority' => 'medium'];

    $this->actingAs($manager)->post(route('tasks.store', $project), $payload)->assertRedirect();
    $this->actingAs($manager)->post(route('tasks.store', $project), [...$payload, 'title' => 'Next rank'])->assertRedirect();

    expect(Task::query()->where('project_id', $project->id)->orderBy('rank')->pluck('rank')->all())
        ->toBe([1000, 2000]);
});
