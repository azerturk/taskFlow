<?php

use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\Sanctum;
use Modules\Activity\Data\ActivityFiltersData;
use Modules\Activity\Enums\ActivityEvent;
use Modules\Activity\Services\ActivityRecorder;
use Modules\Projects\Enums\ProjectMemberRole;
use Modules\Projects\Models\Project;
use Modules\Projects\Services\ProjectMemberService;
use Modules\Tasks\Models\Task;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->withoutVite();
});

function scopedActivityFilterContext(): array
{
    $manager = User::factory()->asProjectManager()->create();
    $member = User::factory()->asMember()->create();
    $foreignOwner = User::factory()->asProjectManager()->create();
    $project = Project::factory()->active()->create(['owner_id' => $manager->id]);
    $foreignProject = Project::factory()->active()->create(['owner_id' => $foreignOwner->id]);
    app(ProjectMemberService::class)->addMember($project, $member, ProjectMemberRole::Member, actor: $manager);
    $task = Task::factory()->for($project)->for($manager, 'creator')->create();
    $foreignTask = Task::factory()->for($foreignProject)->for($foreignOwner, 'creator')->create();
    app(ActivityRecorder::class)->record(ActivityEvent::TaskCreated, $manager, $task, [
        'project_id' => $project->id,
        'task_id' => $task->id,
        'task_number' => $task->number,
        'task_title' => $task->title,
    ]);
    app(ActivityRecorder::class)->record(ActivityEvent::TaskCreated, $foreignOwner, $foreignTask, [
        'project_id' => $foreignProject->id,
        'task_id' => $foreignTask->id,
        'task_number' => $foreignTask->number,
        'task_title' => $foreignTask->title,
    ]);

    return compact('manager', 'member', 'foreignOwner', 'project', 'foreignProject', 'task', 'foreignTask');
}

test('activity Web aliases and API canonical filters share one immutable DTO and scope', function (): void {
    extract(scopedActivityFilterContext());
    $today = today()->toDateString();
    $filters = ActivityFiltersData::fromArray(['date_from' => $today, 'date_to' => $today]);

    expect($filters->dateFrom)->toBeInstanceOf(CarbonImmutable::class)
        ->and($filters->dateTo)->toBeInstanceOf(CarbonImmutable::class);

    Sanctum::actingAs($member, ['activity:read']);
    $this->getJson('/api/v1/activity?'.http_build_query([
        'event' => ActivityEvent::TaskCreated->value,
        'project_id' => $project->id,
        'task_id' => $task->id,
        'actor_id' => $manager->id,
        'date_from' => $today,
        'date_to' => $today,
    ]))->assertOk()->assertJsonPath('data.0.task_id', $task->id)->assertJsonCount(1, 'data');

    $this->actingAs($member)->get(route('activity.index', [
        'event' => ActivityEvent::TaskCreated->value,
        'project' => $project->id,
        'task' => $task->id,
        'actor' => $manager->id,
        'date_from' => $today,
        'date_to' => $today,
    ]))->assertOk()->assertSee($task->number)->assertDontSee($foreignTask->number);
});

test('hidden and nonexistent Activity identifiers have the same safe API result shape', function (): void {
    extract(scopedActivityFilterContext());
    Sanctum::actingAs($member, ['activity:read']);
    $missing = 999999;

    foreach ([
        ['project_id', $foreignProject->id],
        ['task_id', $foreignTask->id],
        ['actor_id', $foreignOwner->id],
    ] as [$field, $hiddenId]) {
        $hidden = $this->getJson('/api/v1/activity?'.http_build_query([$field => $hiddenId]))->assertOk();
        $nonexistent = $this->getJson('/api/v1/activity?'.http_build_query([$field => $missing]))->assertOk();

        expect($hidden->json('data'))->toBe([])
            ->and($nonexistent->json('data'))->toBe([])
            ->and(array_keys($hidden->json()))->toBe(array_keys($nonexistent->json()))
            ->and(array_keys($hidden->json('meta')))->toBe(array_keys($nonexistent->json('meta')));
    }
});

test('Activity adapters reject invalid aliases scalars dates and unknown filters without a 500', function (): void {
    extract(scopedActivityFilterContext());
    Sanctum::actingAs($member, ['activity:read']);

    $this->getJson('/api/v1/activity?project_id=invalid')->assertUnprocessable()->assertJsonValidationErrors('project_id');
    $this->getJson('/api/v1/activity?date_from=invalid')->assertUnprocessable()->assertJsonValidationErrors('date_from');
    $this->getJson('/api/v1/activity?project='.$project->id)->assertUnprocessable()->assertJsonValidationErrors('project');
    $this->getJson('/api/v1/activity?unknown=value')->assertUnprocessable()->assertJsonValidationErrors('unknown');

    $this->actingAs($member)
        ->from(route('activity.index'))
        ->get(route('activity.index', ['project' => 'invalid', 'date_from' => 'invalid']))
        ->assertRedirect(route('activity.index'))
        ->assertSessionHasErrors(['project', 'date_from']);
    $this->from(route('activity.index'))
        ->get(route('activity.index', ['project_id' => $project->id]))
        ->assertRedirect(route('activity.index'))
        ->assertSessionHasErrors('project_id');
});
