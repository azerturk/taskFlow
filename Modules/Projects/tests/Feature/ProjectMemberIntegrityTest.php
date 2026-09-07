<?php

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Modules\Projects\Data\UpdateProjectMemberData;
use Modules\Projects\Enums\ProjectMemberRole;
use Modules\Projects\Enums\ProjectStatus;
use Modules\Projects\Models\Project;
use Modules\Projects\Models\ProjectMember;
use Modules\Projects\Services\ProjectMemberService;
use Modules\Tasks\Models\Task;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

function projectWithOwner(): array
{
    $owner = User::factory()->asProjectManager()->create();
    $project = Project::factory()->active()->create(['owner_id' => $owner->id]);
    app(ProjectMemberService::class)->addMember($project, $owner, ProjectMemberRole::Manager, actor: $owner);

    return [$project, $owner];
}

it('prevents duplicate memberships and preserves the owner manager membership', function (): void {
    [$project, $owner] = projectWithOwner();
    $service = app(ProjectMemberService::class);

    expect(fn () => $service->addMember($project, $owner, ProjectMemberRole::Manager, actor: $owner))
        ->toThrow(LogicException::class)
        ->and(fn () => $service->addMember(Project::factory()->active()->create(['owner_id' => $owner->id]), $owner, ProjectMemberRole::Member, actor: $owner))
        ->toThrow(LogicException::class)
        ->and(fn () => $service->updateMemberRole($project, $owner, new UpdateProjectMemberData(ProjectMemberRole::Member), $owner))
        ->toThrow(LogicException::class)
        ->and(fn () => $service->removeMember($project, $owner, $owner))
        ->toThrow(LogicException::class)
        ->and(ProjectMember::query()->where('project_id', $project->id)->where('user_id', $owner->id)->firstOrFail()->member_role)
        ->toBe(ProjectMemberRole::Manager);
});

it('allows a context manager to manage roles but denies an outsider through the web flow', function (): void {
    [$project, $owner] = projectWithOwner();
    $manager = User::factory()->asMember()->create();
    $member = User::factory()->asMember()->create();
    $outsider = User::factory()->asMember()->create();
    $service = app(ProjectMemberService::class);
    $service->addMember($project, $manager, ProjectMemberRole::Manager, actor: $owner);
    $service->addMember($project, $member, ProjectMemberRole::Member, actor: $owner);

    $this->actingAs($manager)
        ->patch(route('projects.members.update', [$project, $member]), ['member_role' => ProjectMemberRole::Manager->value])
        ->assertRedirect();

    expect(ProjectMember::query()->where('project_id', $project->id)->where('user_id', $member->id)->firstOrFail()->member_role)
        ->toBe(ProjectMemberRole::Manager);

    $this->actingAs($outsider)
        ->patch(route('projects.members.update', [$project, $member]), ['member_role' => ProjectMemberRole::Member->value])
        ->assertNotFound();
});

it('reports open assignment counts as an API conflict and leaves the membership and activity unchanged', function (): void {
    [$project, $owner] = projectWithOwner();
    $member = User::factory()->asMember()->create();
    app(ProjectMemberService::class)->addMember($project, $member, ProjectMemberRole::Member, actor: $owner);
    Task::factory()->for($project)->create(['creator_id' => $owner->id, 'assignee_id' => $member->id]);
    $before = DB::table('activity_log')->where('event', 'project.member_removed')->count();

    Sanctum::actingAs($owner, ['projects:write']);
    $this->deleteJson(route('api.v1.projects.members.destroy', [$project, $member]))
        ->assertConflict()
        ->assertJsonPath('code', 'member_has_open_assignments')
        ->assertJsonPath('meta.open_assignment_count', 1)
        ->assertJsonPath('errors.user_id.0', 'Reassign or unassign open work before removal.');

    expect(ProjectMember::query()->where('project_id', $project->id)->where('user_id', $member->id)->exists())->toBeTrue()
        ->and(DB::table('activity_log')->where('event', 'project.member_removed')->count())->toBe($before);
});

it('removes an unassigned member transactionally and records the removal history', function (): void {
    [$project, $owner] = projectWithOwner();
    $member = User::factory()->asMember()->create();
    $service = app(ProjectMemberService::class);
    $service->addMember($project, $member, ProjectMemberRole::Member, actor: $owner);

    $service->removeMember($project, $member, $owner);

    expect(ProjectMember::query()->where('project_id', $project->id)->where('user_id', $member->id)->exists())->toBeFalse()
        ->and(DB::table('activity_log')->where('event', 'project.member_removed')->where('subject_id', $project->id)->count())->toBe(1);
});

it('keeps completed and archived projects read-only for every membership mutation', function (ProjectStatus $status): void {
    [$project, $owner] = projectWithOwner();
    $project->update(['status' => $status]);
    $member = User::factory()->asMember()->create();
    ProjectMember::factory()->for($project)->for($member)->create(['member_role' => ProjectMemberRole::Member]);
    $service = app(ProjectMemberService::class);

    expect(fn () => $service->addMember($project, User::factory()->asMember()->create(), ProjectMemberRole::Member, actor: $owner))->toThrow(LogicException::class)
        ->and(fn () => $service->updateMemberRole($project, $member, new UpdateProjectMemberData(ProjectMemberRole::Manager), $owner))->toThrow(LogicException::class)
        ->and(fn () => $service->removeMember($project, $member, $owner))->toThrow(LogicException::class);
})->with([[ProjectStatus::Completed], [ProjectStatus::Archived]]);

it('returns only active non-members from the available-user query', function (): void {
    [$project, $owner] = projectWithOwner();
    $member = User::factory()->asMember()->create();
    $suspended = User::factory()->asMember()->suspended()->create();
    $available = User::factory()->asMember()->create();
    app(ProjectMemberService::class)->addMember($project, $member, ProjectMemberRole::Member, actor: $owner);

    $ids = app(ProjectMemberService::class)->availableUsers($project)->pluck('id');

    expect($ids)->toContain($available->id)
        ->not->toContain($owner->id)
        ->not->toContain($member->id)
        ->not->toContain($suspended->id)
        ->and(fn () => app(ProjectMemberService::class)->addMember($project, $suspended, ProjectMemberRole::Member, actor: $owner))
        ->toThrow(LogicException::class);
});
