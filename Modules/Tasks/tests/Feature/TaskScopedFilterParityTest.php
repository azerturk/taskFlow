<?php

use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Modules\Projects\Enums\ProjectMemberRole;
use Modules\Projects\Models\Project;
use Modules\Projects\Services\ProjectMemberService;
use Modules\Tasks\Data\TaskFiltersData;
use Modules\Tasks\Enums\TaskPriority;
use Modules\Tasks\Enums\TaskStatus;
use Modules\Tasks\Livewire\TaskFilters;
use Modules\Tasks\Models\Task;
use Modules\Tasks\Models\TaskLabel;
use Modules\Tasks\Services\TaskQueryService;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->withoutVite();
});

function scopedTaskFilterContext(): array
{
    $manager = User::factory()->asProjectManager()->create();
    $member = User::factory()->asMember()->create();
    $foreignOwner = User::factory()->asProjectManager()->create();
    $project = Project::factory()->active()->create(['owner_id' => $manager->id]);
    $foreignProject = Project::factory()->active()->create(['owner_id' => $foreignOwner->id]);
    app(ProjectMemberService::class)->addMember($project, $member, ProjectMemberRole::Member, actor: $manager);

    $visibleLabel = TaskLabel::create(['project_id' => $project->id, 'name' => 'Visible label', 'slug' => 'visible-label', 'color' => '#3B82F6']);
    $hiddenLabel = TaskLabel::create(['project_id' => $foreignProject->id, 'name' => 'Hidden label', 'slug' => 'hidden-label', 'color' => '#EF4444']);

    $visible = collect(TaskPriority::cases())->mapWithKeys(function (TaskPriority $priority) use ($project, $manager, $visibleLabel): array {
        $task = Task::factory()->for($project)->for($manager, 'creator')->create([
            'title' => 'Needle '.$priority->value,
            'status' => TaskStatus::Backlog,
            'priority' => $priority,
            'assignee_id' => $manager->id,
            'due_at' => '2026-09-10',
        ]);
        $task->labels()->attach($visibleLabel);

        return [$priority->value => $task];
    });

    $hiddenParent = Task::factory()->for($foreignProject)->for($foreignOwner, 'creator')->create([
        'title' => 'Hidden parent',
        'assignee_id' => $foreignOwner->id,
    ]);
    $hiddenParent->labels()->attach($hiddenLabel);

    return compact('manager', 'member', 'foreignOwner', 'project', 'foreignProject', 'visibleLabel', 'hiddenLabel', 'visible', 'hiddenParent');
}

test('canonical priority order and immutable due boundaries are repository owned', function (): void {
    extract(scopedTaskFilterContext());
    Sanctum::actingAs($member, ['tasks:read']);

    $ascending = $this->getJson('/api/v1/tasks?sort=priority&per_page=100')->assertOk();
    $descending = $this->getJson('/api/v1/tasks?sort=-priority&per_page=100')->assertOk();
    $backlog = $this->getJson('/api/v1/projects/'.$project->id.'/backlog?sort=priority&per_page=100')->assertOk();
    $board = $this->getJson('/api/v1/projects/'.$project->id.'/board?sort=priority')->assertOk();

    expect(collect($ascending->json('data'))->pluck('priority')->all())
        ->toBe(['low', 'medium', 'high', 'urgent'])
        ->and(collect($descending->json('data'))->pluck('priority')->all())
        ->toBe(['urgent', 'high', 'medium', 'low'])
        ->and(collect($backlog->json('data'))->pluck('priority')->all())
        ->toBe(['low', 'medium', 'high', 'urgent'])
        ->and(collect($board->json('data.backlog'))->pluck('priority')->all())
        ->toBe(['low', 'medium', 'high', 'urgent']);

    $filters = TaskFiltersData::fromArray(['due_after' => '2026-09-01', 'due_before' => '2026-09-30']);
    expect($filters->dueAfter)->toBeInstanceOf(CarbonImmutable::class)
        ->and($filters->dueBefore)->toBeInstanceOf(CarbonImmutable::class)
        ->and($filters->dueAfter?->toDateString())->toBe('2026-09-01')
        ->and($filters->dueBefore?->toDateString())->toBe('2026-09-30');
});

test('the same validated task filters drive API Web Livewire backlog and board reads', function (): void {
    extract(scopedTaskFilterContext());
    $high = $visible['high'];
    $low = $visible['low'];

    Sanctum::actingAs($member, ['tasks:read']);
    $query = 'search=Needle&priorities[]=high&due_after=2026-09-01&due_before=2026-09-30&sort=priority';
    $this->getJson('/api/v1/tasks?'.$query)
        ->assertOk()->assertJsonPath('data.0.id', $high->id)->assertJsonCount(1, 'data');
    $this->getJson('/api/v1/projects/'.$project->id.'/backlog?'.$query)
        ->assertOk()->assertJsonPath('data.0.id', $high->id)->assertJsonCount(1, 'data');
    $this->getJson('/api/v1/projects/'.$project->id.'/board?'.$query)
        ->assertOk()->assertJsonPath('data.backlog.0.id', $high->id)->assertJsonCount(1, 'data.backlog');

    $this->actingAs($member);
    $webQuery = 'q=Needle&priorities[]=high&due_after=2026-09-01&due_before=2026-09-30&sort=priority';
    $this->get('/projects/'.$project->id.'/backlog?'.$webQuery)->assertOk()->assertSee($high->title)->assertDontSee($low->title);
    $this->get('/projects/'.$project->id.'/board?'.$webQuery)->assertOk()->assertSee($high->title)->assertDontSee($low->title);
    Livewire::test(TaskFilters::class)
        ->set('q', 'Needle')
        ->set('priorities', ['high'])
        ->set('dueAfter', '2026-09-01')
        ->set('dueBefore', '2026-09-30')
        ->set('sort', 'priority')
        ->assertViewHas('tasks', fn ($tasks): bool => $tasks->pluck('id')->all() === [$high->id]);
});

test('hidden and nonexistent task filter identifiers are indistinguishable and options do not leak', function (): void {
    extract(scopedTaskFilterContext());
    Sanctum::actingAs($member, ['tasks:read']);
    $missing = 999999;
    $pairs = [
        [['project_id' => $foreignProject->id], ['project_id' => $missing]],
        [['assignee_id' => $foreignOwner->id], ['assignee_id' => $missing]],
        [['reporter_id' => $foreignOwner->id], ['reporter_id' => $missing]],
        [['parent_id' => $hiddenParent->id], ['parent_id' => $missing]],
        [['label_ids' => [$hiddenLabel->id]], ['label_ids' => [$missing]]],
    ];

    foreach ($pairs as [$hiddenFilter, $missingFilter]) {
        $hidden = $this->getJson('/api/v1/tasks?'.http_build_query($hiddenFilter))->assertOk();
        $nonexistent = $this->getJson('/api/v1/tasks?'.http_build_query($missingFilter))->assertOk();

        expect($hidden->json('data'))->toBe([])
            ->and($nonexistent->json('data'))->toBe([])
            ->and(array_keys($hidden->json()))->toBe(array_keys($nonexistent->json()))
            ->and(array_keys($hidden->json('meta')))->toBe(array_keys($nonexistent->json('meta')));
    }

    $options = app(TaskQueryService::class)->filterOptionsFor($member);
    expect($options['projects']->pluck('id')->all())->not->toContain($foreignProject->id)
        ->and($options['assignees']->pluck('id')->all())->not->toContain($foreignOwner->id)
        ->and($options['reporters']->pluck('id')->all())->not->toContain($foreignOwner->id)
        ->and($options['parents']->pluck('id')->all())->not->toContain($hiddenParent->id)
        ->and($options['labels']->pluck('id')->all())->not->toContain($hiddenLabel->id);
});

test('task adapters reject invalid and channel-specific query parameters before repository use', function (): void {
    extract(scopedTaskFilterContext());
    Sanctum::actingAs($member, ['tasks:read']);

    $this->getJson('/api/v1/tasks?project_id=invalid')->assertUnprocessable()->assertJsonValidationErrors('project_id');
    $this->getJson('/api/v1/tasks?q=Needle')->assertUnprocessable()->assertJsonValidationErrors('q');
    $this->getJson('/api/v1/projects/'.$project->id.'/board?project_id='.$project->id)->assertUnprocessable()->assertJsonValidationErrors('project_id');
    $this->getJson('/api/v1/projects/'.$project->id.'/backlog?due_after=not-a-date')->assertUnprocessable()->assertJsonValidationErrors('due_after');

    $this->actingAs($member)
        ->from(route('tasks.index'))
        ->get(route('tasks.index', ['search' => 'Needle']))
        ->assertRedirect(route('tasks.index'))
        ->assertSessionHasErrors('search');
});
