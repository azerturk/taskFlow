<?php

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\DB;
use Modules\Projects\Data\ChangeProjectStatusData;
use Modules\Projects\Data\CreateProjectData;
use Modules\Projects\Data\UpdateProjectData;
use Modules\Projects\Enums\ProjectMemberRole;
use Modules\Projects\Enums\ProjectStatus;
use Modules\Projects\Models\Project;
use Modules\Projects\Services\ProjectMemberService;
use Modules\Projects\Services\ProjectService;
use Modules\Tasks\Data\CreateTaskData;
use Modules\Tasks\Enums\TaskPriority;
use Modules\Tasks\Services\TaskService;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

function projectForLifecycle(User $owner): Project
{
    return app(ProjectService::class)->create($owner, new CreateProjectData('Payments API', 'PAY', 'Project description', null, null));
}

it('validates uppercase project keys and enforces database uniqueness', function (): void {
    $admin = User::factory()->asAdmin()->create();

    $this->actingAs($admin)->post(route('projects.store'), [
        'name' => 'Payments API', 'key' => ' pay ', 'description' => null,
    ])->assertRedirect();

    expect(Project::query()->sole()->key)->toBe('PAY');

    $this->actingAs($admin)->post(route('projects.store'), [
        'name' => 'Payments duplicate', 'key' => 'PAY', 'description' => null,
    ])->assertSessionHasErrors('key');

    $this->actingAs($admin)->post(route('projects.store'), [
        'name' => 'Bad key', 'key' => '1bad', 'description' => null,
    ])->assertSessionHasErrors('key');
});

it('implements the documented lifecycle transition table and records safe old/new status activity', function (): void {
    $admin = User::factory()->asAdmin()->create();
    $project = projectForLifecycle($admin);
    $service = app(ProjectService::class);

    expect(fn () => $service->changeStatus($project, new ChangeProjectStatusData(ProjectStatus::Completed), $admin))->toThrow(LogicException::class);

    $project = $service->changeStatus($project, new ChangeProjectStatusData(ProjectStatus::Active), $admin);
    $project = $service->changeStatus($project, new ChangeProjectStatusData(ProjectStatus::Completed), $admin);
    $project = $service->changeStatus($project, new ChangeProjectStatusData(ProjectStatus::Active), $admin);
    $project = $service->changeStatus($project, new ChangeProjectStatusData(ProjectStatus::Archived), $admin);

    expect($project->status)->toBe(ProjectStatus::Archived)
        ->and(fn () => $service->changeStatus($project, new ChangeProjectStatusData(ProjectStatus::Active), $admin))->toThrow(LogicException::class);

    $activity = DB::table('activity_log')->where('event', 'project.status_changed')->latest('id')->first();
    expect($activity->properties)->toContain('old_status')->toContain('new_status');
});

it('allocates project-local issue numbers transactionally and freezes the key after the first issue', function (): void {
    $admin = User::factory()->asAdmin()->create();
    $project = projectForLifecycle($admin);
    $projects = app(ProjectService::class);
    $project = $projects->changeStatus($project, new ChangeProjectStatusData(ProjectStatus::Active), $admin);
    $tasks = app(TaskService::class);

    $first = $tasks->create($admin, $project, new CreateTaskData('First issue', null, null, TaskPriority::Medium, null));
    $second = $tasks->create($admin, $project, new CreateTaskData('Second issue', null, null, TaskPriority::Medium, null));

    expect($first->number)->toBe('PAY-1')
        ->and($second->number)->toBe('PAY-2')
        ->and($project->fresh()->next_issue_number)->toBe(3)
        ->and(fn () => $projects->update($project->fresh(), new UpdateProjectData('Payments API', 'NEW', null, null, null), $admin))->toThrow(LogicException::class);
});

it('rejects completed and archived project mutations at direct service boundaries', function (ProjectStatus $status): void {
    $admin = User::factory()->asAdmin()->create();
    $project = projectForLifecycle($admin);
    $projects = app(ProjectService::class);
    $project = $projects->changeStatus($project, new ChangeProjectStatusData(ProjectStatus::Active), $admin);
    if ($status === ProjectStatus::Completed) {
        $project = $projects->changeStatus($project, new ChangeProjectStatusData(ProjectStatus::Completed), $admin);
    } else {
        $project = $projects->changeStatus($project, new ChangeProjectStatusData(ProjectStatus::Archived), $admin);
    }

    expect(fn () => $projects->update($project, new UpdateProjectData('Changed name', null, null, null, null), $admin))->toThrow(LogicException::class)
        ->and(fn () => app(ProjectMemberService::class)->addMember($project, User::factory()->asMember()->create(), ProjectMemberRole::Member, actor: $admin))->toThrow(LogicException::class);
})->with([[ProjectStatus::Completed], [ProjectStatus::Archived]]);
