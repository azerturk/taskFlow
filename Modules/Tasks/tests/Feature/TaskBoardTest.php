<?php

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\Sanctum;
use Modules\Projects\Enums\ProjectMemberRole;
use Modules\Projects\Models\Project;
use Modules\Projects\Services\ProjectMemberService;
use Modules\Tasks\Enums\TaskStatus;
use Modules\Tasks\Models\Task;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});
function boardContext(): array
{
    $manager = User::factory()->asProjectManager()->create();
    $member = User::factory()->asMember()->create();
    $outsider = User::factory()->asMember()->create();
    $project = Project::factory()->active()->create(['owner_id' => $manager->id]);
    $other = Project::factory()->active()->create(['owner_id' => $manager->id]);
    app(ProjectMemberService::class)->addMember($project, $member, ProjectMemberRole::Member, actor: $manager);
    $backlog = Task::factory()->for($project)->for($manager, 'creator')->create(['status' => TaskStatus::Backlog, 'rank' => 1000]);
    $todo = Task::factory()->for($project)->for($manager, 'creator')->create(['status' => TaskStatus::Todo, 'rank' => 1000]);
    $foreign = Task::factory()->for($other)->for($manager, 'creator')->create(['status' => TaskStatus::Backlog, 'rank' => 1000]);

    return [$manager, $member, $outsider, $project, $backlog, $todo, $foreign];
}
test('board is project scoped, eager card data is rendered and API groups fixed columns', function () {
    [$manager,$member,$outsider,$project,$backlog,$todo,$foreign] = boardContext();
    $this->actingAs($member)->get(route('projects.board', $project))->assertOk()->assertSee($backlog->title)->assertSee($todo->title)->assertDontSee($foreign->title)->assertSee('data-board', false);
    Sanctum::actingAs($member, ['tasks:read']);
    $this->getJson('/api/v1/projects/'.$project->id.'/board')->assertOk()->assertJsonPath('data.backlog.0.id', $backlog->id)->assertJsonPath('data.todo.0.id', $todo->id);
    Sanctum::actingAs($outsider, ['tasks:read']);
    $this->getJson('/api/v1/projects/'.$project->id.'/board')->assertNotFound();
});
test('board fallback uses service transitions and manager-only no-JavaScript reorder controls', function () {
    [$manager, $member, , $project, $backlog] = boardContext();
    $backlog->update(['assignee_id' => $member->id]);
    Task::factory()->for($project)->for($manager, 'creator')->create(['status' => TaskStatus::Backlog, 'rank' => 2000]);

    $this->actingAs($member)->get(route('projects.board', $project))
        ->assertOk()
        ->assertSee('<option value="todo">Todo</option>', false)
        ->assertSee('<option value="cancelled">Cancelled</option>', false)
        ->assertDontSee('data-rank-form', false);

    $this->actingAs($manager)->get(route('projects.board', $project))
        ->assertOk()
        ->assertSee('data-rank-form', false)
        ->assertSee('data-rank-url="'.route('tasks.reorder', $backlog).'"', false);
});

test('backlog only shows backlog status and exposes reorder fallback to managers only', function (): void {
    [$manager, $member, , $project, $backlog, $todo] = boardContext();

    $this->actingAs($member)->get(route('projects.backlog', $project))
        ->assertOk()
        ->assertSee($backlog->title)
        ->assertDontSee($todo->title)
        ->assertDontSee('name="after_task_id"', false);

    Task::factory()->for($project)->for($manager, 'creator')->create(['status' => TaskStatus::Backlog, 'rank' => 2000]);
    $this->actingAs($manager)->get(route('projects.backlog', $project))
        ->assertOk()
        ->assertSee('name="after_task_id"', false)
        ->assertSee('name="expected_version"', false);
});

test('status routes remain policy and canonical version-conflict protected', function () {
    [$manager, $member, , , $backlog] = boardContext();
    $this->actingAs($member)->patch(route('tasks.status', $backlog), ['status' => 'todo', 'expected_version' => $backlog->version])->assertForbidden();
    $this->actingAs($manager)->patch(route('tasks.status', $backlog), ['status' => 'todo', 'expected_version' => $backlog->version])->assertRedirect();
    expect($backlog->fresh()->status)->toBe(TaskStatus::Todo);
    $stale = $backlog->version;
    Sanctum::actingAs($manager, ['tasks:write']);
    $this->patchJson('/api/v1/tasks/'.$backlog->id.'/status', ['status' => 'in_progress', 'expected_version' => $stale])
        ->assertStatus(409)
        ->assertJsonPath('code', 'task_version_conflict');

    $this->actingAs($manager)
        ->from(route('projects.board', $backlog->project_id))
        ->patch(route('tasks.status', $backlog), ['status' => 'in_progress', 'expected_version' => $stale])
        ->assertRedirect(route('projects.board', $backlog->project_id))
        ->assertSessionHas('error', 'This task changed while you were working. Refresh the page and try again.');
});

test('board fetch mutations return no-content instead of redirecting a PATCH request', function (): void {
    [$manager, , , $project, $backlog] = boardContext();
    $neighbor = Task::factory()->for($project)->for($manager, 'creator')->create([
        'status' => TaskStatus::Backlog,
        'rank' => 2000,
    ]);

    $this->actingAs($manager)
        ->patchJson(route('tasks.reorder', $neighbor), [
            'after_task_id' => $backlog->id,
            'expected_version' => $neighbor->version,
        ])
        ->assertNoContent();

    $neighbor->refresh();
    $this->actingAs($manager)
        ->patchJson(route('tasks.status', $neighbor), [
            'status' => TaskStatus::Todo->value,
            'expected_version' => $neighbor->version,
        ])
        ->assertNoContent();

    expect($neighbor->fresh()->status)->toBe(TaskStatus::Todo);
});
