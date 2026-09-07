<?php

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\Sanctum;
use Modules\Projects\Enums\ProjectMemberRole;
use Modules\Projects\Models\Project;
use Modules\Projects\Services\ProjectMemberService;
use Modules\Tasks\Models\Task;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

function scopedPresentationProject(User $owner): Project
{
    return Project::factory()->active()->create([
        'owner_id' => $owner->id,
        'key' => 'PRES'.fake()->unique()->numerify('##'),
    ]);
}

it('renders a scoped Web project list and detail summary without Blade database queries', function (): void {
    $owner = User::factory()->asProjectManager()->create();
    $outsider = User::factory()->asMember()->create();
    $project = scopedPresentationProject($owner);
    Task::factory()->for($project)->create(['creator_id' => $owner->id]);

    $this->actingAs($owner)
        ->get(route('projects.index', ['q' => $project->key, 'status' => 'active']))
        ->assertOk()
        ->assertSee($project->name)
        ->assertSee($project->key)
        ->assertSee('1 tasks');

    $this->actingAs($owner)
        ->get(route('projects.show', $project))
        ->assertOk()
        ->assertSee('Tasks')
        ->assertSee('1 tasks')
        ->assertSee($project->key);

    $this->actingAs($outsider)
        ->get(route('projects.index'))
        ->assertOk()
        ->assertDontSee($project->name);
});

it('validates Web project list filters and create input with useful errors', function (): void {
    $manager = User::factory()->asProjectManager()->create();

    $this->actingAs($manager)
        ->get(route('projects.index', ['status' => 'not-a-status']))
        ->assertSessionHasErrors('status');

    $this->actingAs($manager)
        ->post(route('projects.store'), ['name' => 'Bad project', 'key' => '1bad'])
        ->assertSessionHasErrors('key');
});

it('returns scoped project resources with counts and exact create/update/status envelopes', function (): void {
    $owner = User::factory()->asProjectManager()->create();
    $project = scopedPresentationProject($owner);
    Task::factory()->for($project)->create(['creator_id' => $owner->id]);

    Sanctum::actingAs($owner, ['projects:read', 'projects:write']);
    $this->getJson(route('api.v1.projects.index', ['search' => $project->key]))
        ->assertOk()
        ->assertJsonPath('data.0.id', $project->id)
        ->assertJsonPath('data.0.key', $project->key)
        ->assertJsonPath('data.0.member_count', 0)
        ->assertJsonPath('data.0.task_count', 1);

    $this->postJson(route('api.v1.projects.store'), [
        'name' => 'Created through API', 'key' => 'API402', 'description' => 'A complete API project.',
    ])->assertCreated()
        ->assertJsonPath('data.key', 'API402')
        ->assertJsonPath('data.member_count', 1)
        ->assertJsonPath('data.task_count', 0);

    $this->putJson(route('api.v1.projects.update', $project), [
        'name' => 'Presented project', 'key' => $project->key, 'description' => 'Updated through API.',
    ])->assertOk()
        ->assertJsonPath('data.name', 'Presented project');

    $this->patchJson(route('api.v1.projects.status', $project), ['status' => 'completed'])
        ->assertOk()
        ->assertJsonPath('data.status', 'completed');
});

it('returns a 404 scoped API response to outsiders and a 403 response for a missing Sanctum ability', function (): void {
    $owner = User::factory()->asProjectManager()->create();
    $outsider = User::factory()->asMember()->create();
    $project = scopedPresentationProject($owner);

    Sanctum::actingAs($outsider, ['projects:read']);
    $this->getJson(route('api.v1.projects.show', $project))->assertNotFound();

    Sanctum::actingAs($owner, ['tasks:read']);
    $this->getJson(route('api.v1.projects.show', $project))->assertForbidden();
});

it('exposes project member resources only to visible actors and uses the same membership service flow', function (): void {
    $owner = User::factory()->asProjectManager()->create();
    $contextManager = User::factory()->asMember()->create();
    $member = User::factory()->asMember()->create();
    $outsider = User::factory()->asMember()->create();
    $project = scopedPresentationProject($owner);
    $members = app(ProjectMemberService::class);
    $members->addMember($project, $contextManager, ProjectMemberRole::Manager, actor: $owner);
    $members->addMember($project, $member, ProjectMemberRole::Member, actor: $owner);

    Sanctum::actingAs($contextManager, ['projects:read', 'projects:write']);
    $this->getJson(route('api.v1.projects.members.index', $project))
        ->assertOk()
        ->assertJsonPath('data.0.user.email', $contextManager->email);

    $this->patchJson(route('api.v1.projects.members.update', [$project, $member]), ['member_role' => 'manager'])
        ->assertOk()
        ->assertJsonPath('data.member_role', 'manager')
        ->assertJsonPath('data.user.email', $member->email);

    $newMember = User::factory()->asMember()->create();
    $this->postJson(route('api.v1.projects.members.store', $project), [
        'user_id' => $newMember->id,
        'member_role' => 'member',
    ])->assertCreated()
        ->assertJsonPath('data.user.email', $newMember->email)
        ->assertJsonPath('data.member_role', 'member');

    $this->deleteJson(route('api.v1.projects.members.destroy', [$project, $newMember]))
        ->assertNoContent();

    Sanctum::actingAs($outsider, ['projects:read']);
    $this->getJson(route('api.v1.projects.members.index', $project))->assertNotFound();
});

it('retires obsolete status aliases and keeps validation errors in the standard 422 shape', function (): void {
    $owner = User::factory()->asProjectManager()->create();
    $project = scopedPresentationProject($owner);

    Sanctum::actingAs($owner, ['projects:write']);
    $this->postJson("/api/v1/projects/{$project->id}/activate")->assertNotFound();
    $this->postJson("/api/v1/projects/{$project->id}/archive")->assertNotFound();
    $this->postJson(route('api.v1.projects.store'), ['name' => 'Bad', 'key' => '1bad'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['key']);
    $this->patchJson(route('api.v1.projects.status', $project), ['status' => 'draft'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status']);
});
