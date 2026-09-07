<?php

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Modules\Projects\Enums\ProjectMemberRole;
use Modules\Projects\Models\Project;
use Modules\Projects\Services\ProjectMemberService;
use Modules\Tasks\Enums\TaskPriority;
use Modules\Tasks\Enums\TaskStatus;
use Modules\Tasks\Livewire\TaskFilters;
use Modules\Tasks\Models\Task;
use Modules\Tasks\Models\TaskLabel;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

function livewireFilterContext(): array
{
    $manager = User::factory()->asProjectManager()->create();
    $member = User::factory()->asMember()->create();
    $outsider = User::factory()->asMember()->create();
    $project = Project::factory()->active()->create(['owner_id' => $manager->id]);
    $hiddenProject = Project::factory()->active()->create(['owner_id' => $outsider->id]);
    app(ProjectMemberService::class)->addMember($project, $member, ProjectMemberRole::Member, actor: $manager);
    $label = TaskLabel::create(['project_id' => $project->id, 'name' => 'Visible label', 'slug' => 'visible-label', 'color' => '#3B82F6']);
    $hiddenLabel = TaskLabel::create(['project_id' => $hiddenProject->id, 'name' => 'Hidden label', 'slug' => 'hidden-label', 'color' => '#3B82F6']);
    $visible = Task::factory()->for($project)->for($manager, 'creator')->create(['title' => 'Visible needle', 'status' => TaskStatus::Todo, 'priority' => TaskPriority::High]);
    $visible->labels()->attach($label);
    $hidden = Task::factory()->for($hiddenProject)->for($outsider, 'creator')->create(['title' => 'Hidden needle']);
    $hidden->labels()->attach($hiddenLabel);

    return [$manager, $member, $outsider, $project, $label, $visible, $hidden];
}

test('task filters restore shared URL state and only render visible options and work', function (): void {
    [, $member, , $project, $label, $visible, $hidden] = livewireFilterContext();

    $this->actingAs($member)->get(route('tasks.index', [
        'q' => 'Visible needle', 'statuses' => ['todo'], 'project_id' => $project->id, 'label_ids' => [$label->id], 'sort' => '-number',
    ]))->assertOk()->assertSee($visible->title)->assertDontSee($hidden->title)->assertSee('Visible label')->assertDontSee('Hidden label');

    Livewire::test(TaskFilters::class)
        ->set('q', 'Visible needle')
        ->set('statuses', ['todo'])
        ->set('projectId', $project->id)
        ->set('labelIds', [$label->id])
        ->set('sort', '-number')
        ->assertSee($visible->title)
        ->assertDontSee($hidden->title)
        ->assertSee('Visible label')
        ->assertDontSee('Hidden label');
});

test('livewire filters reset pagination validate tampered input and match the API result scope', function (): void {
    [$manager, $member, , $project, , $visible, $hidden] = livewireFilterContext();
    Task::factory()->count(12)->for($project)->for($manager, 'creator')->create(['title' => 'Extra visible work']);

    $this->actingAs($member);
    Livewire::test(TaskFilters::class)
        ->call('setPage', 2)
        ->set('q', 'Visible needle')
        ->assertSet('paginators.page', 1)
        ->set('sort', 'not-a-sort')
        ->call('apply')
        ->assertHasErrors('sort');

    Sanctum::actingAs($member, ['tasks:read']);
    $this->getJson('/api/v1/tasks?search=Visible%20needle&statuses[]=todo&project_id='.$project->id.'&sort=-number')
        ->assertOk()
        ->assertJsonPath('data.0.id', $visible->id)
        ->assertJsonMissing(['id' => $hidden->id]);
});

test('task filters use the query service rather than a repository or Eloquent dependency', function (): void {
    $source = file_get_contents(module_path('Tasks', 'app/Livewire/TaskFilters.php'));

    expect($source)->toContain('TaskQueryService')
        ->not->toContain('TaskRepositoryInterface')
        ->not->toContain('Task::query');
});
