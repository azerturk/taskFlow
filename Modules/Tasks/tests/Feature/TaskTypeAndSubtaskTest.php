<?php

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\Sanctum;
use Modules\Projects\Enums\ProjectMemberRole;
use Modules\Projects\Models\Project;
use Modules\Projects\Services\ProjectMemberService;
use Modules\Tasks\Data\ChangeTaskStatusData;
use Modules\Tasks\Data\CreateTaskData;
use Modules\Tasks\Data\UpdateTaskData;
use Modules\Tasks\Enums\TaskPriority;
use Modules\Tasks\Enums\TaskStatus;
use Modules\Tasks\Enums\TaskType;
use Modules\Tasks\Exceptions\InvalidTaskStatusTransition;
use Modules\Tasks\Exceptions\ParentTaskInvalid;
use Modules\Tasks\Http\Resources\TaskResource;
use Modules\Tasks\Models\Task;
use Modules\Tasks\Services\TaskService;
use Modules\Tasks\Services\TaskStatusService;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

function taskTypeProject(User $actor, string $key = 'PAY'): Project
{
    return Project::factory()->active()->create(['owner_id' => $actor->id, 'key' => $key]);
}

function createTypedTask(User $actor, Project $project, string $title, TaskType $type = TaskType::Task, ?int $parentId = null, ?int $assigneeId = null): Task
{
    return app(TaskService::class)->create($actor, $project, new CreateTaskData($title, null, $assigneeId, TaskPriority::Medium, null, $type, $parentId));
}

it('creates standard Task, Bug and Story work, plus a same-project one-level subtask', function (): void {
    $manager = User::factory()->asProjectManager()->create();
    $firstMember = User::factory()->asMember()->create();
    $secondMember = User::factory()->asMember()->create();
    $project = taskTypeProject($manager);
    app(ProjectMemberService::class)->addMember($project, $firstMember, ProjectMemberRole::Member, actor: $manager);
    app(ProjectMemberService::class)->addMember($project, $secondMember, ProjectMemberRole::Member, actor: $manager);
    $parent = createTypedTask($manager, $project, 'Parent bug', TaskType::Bug, null, $firstMember->id);
    $child = createTypedTask($manager, $project, 'Child task', TaskType::Subtask, $parent->id, $secondMember->id);
    $story = createTypedTask($manager, $project, 'Story item', TaskType::Story);
    $resource = (new TaskResource($parent->load(['parent', 'subtasks'])))->resolve();

    expect($child->parent_id)->toBe($parent->id)
        ->and($child->type)->toBe(TaskType::Subtask)
        ->and($child->assignee_id)->toBe($secondMember->id)
        ->and($parent->assignee_id)->toBe($firstMember->id)
        ->and($story->type)->toBe(TaskType::Story)
        ->and($resource['subtasks'][0])->toMatchArray(['id' => $child->id, 'type' => 'subtask']);
});

it('rejects cross-project, missing, nested and standard-task parent combinations', function (): void {
    $manager = User::factory()->asProjectManager()->create();
    $payments = taskTypeProject($manager, 'PAY');
    $operations = taskTypeProject($manager, 'OPS');
    $parent = createTypedTask($manager, $payments, 'Parent');
    $subtask = createTypedTask($manager, $payments, 'Child', TaskType::Subtask, $parent->id);

    expect(fn () => createTypedTask($manager, $operations, 'Foreign', TaskType::Subtask, $parent->id))->toThrow(LogicException::class)
        ->and(fn () => createTypedTask($manager, $payments, 'Missing', TaskType::Subtask, 999999))->toThrow(ParentTaskInvalid::class)
        ->and(fn () => createTypedTask($manager, $payments, 'Nested', TaskType::Subtask, $subtask->id))->toThrow(LogicException::class)
        ->and(fn () => createTypedTask($manager, $payments, 'Invalid parent', TaskType::Task, $parent->id))->toThrow(LogicException::class);
});

it('prevents invalid type changes and protects parent completion while subtasks remain open', function (): void {
    $manager = User::factory()->asProjectManager()->create();
    $project = taskTypeProject($manager);
    $parent = createTypedTask($manager, $project, 'Parent');
    $child = createTypedTask($manager, $project, 'Child', TaskType::Subtask, $parent->id);

    expect(fn () => app(TaskService::class)->update($parent->load('project'), new UpdateTaskData('Parent', null, TaskPriority::Medium, null, TaskType::Subtask, $child->id, true), $manager))->toThrow(LogicException::class);

    $parent->update(['status' => TaskStatus::Review]);
    $currentParent = $parent->fresh()->load('project');
    expect(fn () => app(TaskStatusService::class)->change($currentParent, new ChangeTaskStatusData(TaskStatus::Done, $currentParent->version), $manager))->toThrow(InvalidTaskStatusTransition::class);

    $child->update(['status' => TaskStatus::Done]);
    $currentParent = $parent->fresh()->load('project');
    expect(app(TaskStatusService::class)->change($currentParent, new ChangeTaskStatusData(TaskStatus::Done, $currentParent->version), $manager)->status)->toBe(TaskStatus::Done);
});

it('uses the same typed DTO flow in Web presentation and the API resource', function (): void {
    $manager = User::factory()->asProjectManager()->create();
    $project = taskTypeProject($manager);
    $parent = createTypedTask($manager, $project, 'Parent');

    $this->actingAs($manager)->get(route('tasks.create', $project))->assertOk()->assertSee('Work type');
    Sanctum::actingAs($manager, ['tasks:write']);
    $this->postJson('/api/v1/tasks', [
        'project_id' => $project->id,
        'title' => 'API subtask',
        'priority' => TaskPriority::High->value,
        'type' => TaskType::Subtask->value,
        'parent_id' => $parent->id,
    ])->assertCreated()->assertJsonPath('data.type', 'subtask')->assertJsonPath('data.parent.id', $parent->id);
});
