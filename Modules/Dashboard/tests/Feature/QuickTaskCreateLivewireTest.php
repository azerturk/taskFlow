<?php

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Modules\Dashboard\Livewire\QuickTaskCreate;
use Modules\Projects\Enums\ProjectMemberRole;
use Modules\Projects\Enums\ProjectStatus;
use Modules\Projects\Models\Project;
use Modules\Projects\Services\ProjectMemberService;
use Modules\Tasks\Data\CreateTaskData;
use Modules\Tasks\Enums\TaskPriority;
use Modules\Tasks\Enums\TaskStatus;
use Modules\Tasks\Enums\TaskType;
use Modules\Tasks\Models\Task;
use Modules\Tasks\Models\TaskLabel;
use Modules\Tasks\Services\TaskService;
use Spatie\Activitylog\Models\Activity;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

function quickTaskContext(): array
{
    $manager = User::factory()->asProjectManager()->create();
    $member = User::factory()->asMember()->create();
    $otherManager = User::factory()->asProjectManager()->create();
    $foreignUser = User::factory()->asMember()->create();
    $project = Project::factory()->active()->create(['owner_id' => $manager->id, 'key' => 'QTC']);
    $other = Project::factory()->active()->create(['owner_id' => $otherManager->id, 'key' => 'OTH']);
    $members = app(ProjectMemberService::class);
    $members->addMember($project, $member, ProjectMemberRole::Member, actor: $manager);
    $members->addMember($other, $foreignUser, ProjectMemberRole::Member, actor: $otherManager);
    $label = TaskLabel::create(['project_id' => $project->id, 'name' => 'Dashboard', 'slug' => 'dashboard', 'color' => '#3B82F6']);
    $foreignLabel = TaskLabel::create(['project_id' => $other->id, 'name' => 'Foreign', 'slug' => 'foreign', 'color' => '#EF4444']);
    $parent = app(TaskService::class)->create($manager, $project, new CreateTaskData('Quick parent', null, null, TaskPriority::Medium, null));
    $foreignParent = app(TaskService::class)->create($otherManager, $other, new CreateTaskData('Foreign parent', null, null, TaskPriority::Medium, null));

    return compact('manager', 'member', 'otherManager', 'foreignUser', 'project', 'other', 'label', 'foreignLabel', 'parent', 'foreignParent');
}

test('quick task creation uses the canonical service flow and refreshes project scoped options', function (): void {
    ['member' => $member, 'project' => $project, 'label' => $label, 'parent' => $parent] = quickTaskContext();
    $this->actingAs($member);

    Livewire::test(QuickTaskCreate::class)
        ->assertSee('Choose a project')
        ->set('projectId', $project->id)
        ->assertSee('Dashboard')
        ->assertSee('Quick parent')
        ->set('title', 'Dashboard subtask')
        ->set('type', TaskType::Subtask->value)
        ->set('priority', TaskPriority::High->value)
        ->set('assigneeId', $member->id)
        ->set('parentId', $parent->id)
        ->set('labelIds', [$label->id])
        ->call('submit')
        ->assertSet('title', '')
        ->assertSet('projectId', null)
        ->assertSee('created in Backlog.')
        ->assertSee('wire:loading.attr="disabled"', false);

    $task = Task::query()->where('title', 'Dashboard subtask')->firstOrFail();
    expect($task->project_id)->toBe($project->id)
        ->and($task->creator_id)->toBe($member->id)
        ->and($task->assignee_id)->toBe($member->id)
        ->and($task->type)->toBe(TaskType::Subtask)
        ->and($task->parent_id)->toBe($parent->id)
        ->and($task->priority)->toBe(TaskPriority::High)
        ->and($task->status)->toBe(TaskStatus::Backlog)
        ->and($task->labels()->pluck('task_labels.id')->all())->toBe([$label->id])
        ->and($task->watchers()->pluck('users.id')->all())->toBe([$member->id])
        ->and(Activity::query()->where('event', 'task.created')->where('properties->task_id', $task->id)->count())->toBe(1);
});

test('fixed project context cannot be changed and invalid scoped values do not create work', function (): void {
    extract(quickTaskContext());
    $this->actingAs($member);

    Livewire::test(QuickTaskCreate::class, ['project' => $project])
        ->set('projectId', $other->id)
        ->assertSet('projectId', $project->id)
        ->set('title', 'Tampered user')
        ->set('assigneeId', $foreignUser->id)
        ->call('submit')
        ->assertHasErrors('assigneeId');

    Livewire::test(QuickTaskCreate::class, ['project' => $project])
        ->set('title', 'Tampered label')
        ->set('labelIds', [$foreignLabel->id])
        ->call('submit')
        ->assertHasErrors('labelIds');

    Livewire::test(QuickTaskCreate::class, ['project' => $project])
        ->set('title', 'Tampered parent')
        ->set('type', TaskType::Subtask->value)
        ->set('parentId', $foreignParent->id)
        ->call('submit')
        ->assertHasErrors('parentId');

    expect(Task::query()->whereIn('title', ['Tampered user', 'Tampered label', 'Tampered parent'])->count())->toBe(0);
});

test('foreign and read-only project tampering is denied, and web API and Livewire creates are equivalent', function (): void {
    extract(quickTaskContext());
    $this->actingAs($member);

    $this->post(route('tasks.store', $project), ['title' => 'Web equivalent', 'priority' => 'medium', 'type' => 'task'])->assertRedirect();
    Sanctum::actingAs($member, ['tasks:write']);
    $this->postJson('/api/v1/tasks', ['project_id' => $project->id, 'title' => 'API equivalent', 'priority' => 'medium', 'type' => 'task'])->assertCreated();
    $this->actingAs($member);

    Livewire::test(QuickTaskCreate::class)
        ->set('projectId', $other->id)
        ->set('title', 'Tampered foreign project')
        ->call('submit')
        ->assertHasErrors('projectId')
        ->assertSee('Tasks cannot be created in the selected project.');

    $project->update(['status' => ProjectStatus::Completed]);
    Livewire::test(QuickTaskCreate::class)
        ->set('projectId', $project->id)
        ->set('title', 'Tampered read only project')
        ->call('submit')
        ->assertForbidden();

    $project->update(['status' => ProjectStatus::Active]);
    Livewire::test(QuickTaskCreate::class, ['project' => $project])->set('title', 'Livewire equivalent')->call('submit');

    $tasks = Task::query()->whereIn('title', ['Web equivalent', 'API equivalent', 'Livewire equivalent'])->get();
    expect($tasks)->toHaveCount(3);
    foreach ($tasks as $task) {
        expect($task->project_id)->toBe($project->id)
            ->and($task->creator_id)->toBe($member->id)
            ->and($task->status)->toBe(TaskStatus::Backlog)
            ->and($task->watchers()->pluck('users.id')->all())->toBe([$member->id])
            ->and(Activity::query()->where('event', 'task.created')->where('properties->task_id', $task->id)->count())->toBe(1);
    }
});
