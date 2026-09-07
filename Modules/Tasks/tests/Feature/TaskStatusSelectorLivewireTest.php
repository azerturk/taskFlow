<?php

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Livewire\Livewire;
use Modules\Projects\Enums\ProjectMemberRole;
use Modules\Projects\Models\Project;
use Modules\Projects\Services\ProjectMemberService;
use Modules\Tasks\Data\ChangeTaskStatusData;
use Modules\Tasks\Enums\TaskStatus;
use Modules\Tasks\Livewire\TaskStatusSelector;
use Modules\Tasks\Models\Task;
use Modules\Tasks\Services\TaskStatusService;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

function statusSelectorContext(TaskStatus $status = TaskStatus::Backlog): array
{
    $manager = User::factory()->asProjectManager()->create();
    $assignee = User::factory()->asMember()->create();
    $project = Project::factory()->active()->create(['owner_id' => $manager->id]);
    app(ProjectMemberService::class)->addMember($project, $assignee, ProjectMemberRole::Member, actor: $manager);
    $task = Task::factory()->for($project)->for($manager, 'creator')->create(['assignee_id' => $assignee->id, 'status' => $status]);

    return [$manager, $assignee, $project, $task];
}

test('the status selector renders service-provided transitions and submits the versioned service flow', function (): void {
    [$manager, , , $task] = statusSelectorContext();
    $this->actingAs($manager);

    Livewire::test(TaskStatusSelector::class, ['task' => $task])
        ->assertSee('Todo')
        ->assertDontSee('In Progress')
        ->set('status', TaskStatus::Todo->value)
        ->call('change')
        ->assertSee('Task status updated.')
        ->assertSet('expectedVersion', $task->version + 1);

    expect($task->fresh()->status)->toBe(TaskStatus::Todo);
});

test('the selector reports invalid and stale requests through canonical validation and conflict rules', function (): void {
    [$manager, , , $task] = statusSelectorContext();
    $this->actingAs($manager);

    Livewire::test(TaskStatusSelector::class, ['task' => $task])
        ->set('status', 'tampered')
        ->call('change')
        ->assertHasErrors('status');

    $component = Livewire::test(TaskStatusSelector::class, ['task' => $task]);
    app(TaskStatusService::class)->change($task->fresh()->load('project'), new ChangeTaskStatusData(TaskStatus::Todo, $task->version), $manager);
    $component->set('status', TaskStatus::Todo->value)
        ->call('change')
        ->assertHasErrors('status')
        ->assertSee('This task changed. Refresh and try again.');
});

test('terminal reopen remains manager-only in the status selector', function (): void {
    [, $assignee, , $task] = statusSelectorContext(TaskStatus::Done);
    $this->actingAs($assignee);

    Livewire::test(TaskStatusSelector::class, ['task' => $task])
        ->assertSee('No status transition is currently available.')
        ->set('status', TaskStatus::InProgress->value)
        ->call('change')
        ->assertHasErrors('status');
});
