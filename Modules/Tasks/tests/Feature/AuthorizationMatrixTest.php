<?php

use App\Enums\AccountStatus;
use App\Enums\PermissionName;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Media\Models\Media;
use Modules\Projects\Enums\ProjectMemberRole;
use Modules\Projects\Enums\ProjectStatus;
use Modules\Projects\Models\Project;
use Modules\Projects\Services\ProjectMemberService;
use Modules\Tasks\Data\ChangeTaskStatusData;
use Modules\Tasks\Data\ReorderTaskData;
use Modules\Tasks\Data\UpdateTaskData;
use Modules\Tasks\Enums\TaskPriority;
use Modules\Tasks\Enums\TaskStatus;
use Modules\Tasks\Exceptions\InvalidTaskStatusTransition;
use Modules\Tasks\Models\Task;
use Modules\Tasks\Models\TaskAttachment;
use Modules\Tasks\Models\TaskComment;
use Modules\Tasks\Policies\TaskPolicy;
use Modules\Tasks\Services\TaskAssignmentService;
use Modules\Tasks\Services\TaskAttachmentService;
use Modules\Tasks\Services\TaskCommentService;
use Modules\Tasks\Services\TaskRankService;
use Modules\Tasks\Services\TaskService;
use Modules\Tasks\Services\TaskStatusService;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

/** @return array<string, mixed> */
function contextualAuthorizationFixture(ProjectStatus $status = ProjectStatus::Active): array
{
    $admin = User::factory()->asAdmin()->create();
    $owner = User::factory()->asMember()->create();
    $memberManager = User::factory()->asMember()->create();
    $globalManager = User::factory()->asProjectManager()->create();
    $reporter = User::factory()->asMember()->create();
    $assignee = User::factory()->asMember()->create();
    $member = User::factory()->asMember()->create();
    $outsider = User::factory()->asMember()->create();
    $foreignOwner = User::factory()->asMember()->create();
    $project = Project::factory()->active()->create(['owner_id' => $owner->id]);
    $foreignProject = Project::factory()->active()->create(['owner_id' => $foreignOwner->id]);
    $members = app(ProjectMemberService::class);

    foreach ([$owner, $memberManager, $globalManager] as $manager) {
        $members->addMember($project, $manager, ProjectMemberRole::Manager, actor: $admin);
    }
    foreach ([$reporter, $assignee, $member] as $projectMember) {
        $members->addMember($project, $projectMember, ProjectMemberRole::Member, actor: $admin);
    }
    $members->addMember($foreignProject, $foreignOwner, ProjectMemberRole::Manager, actor: $admin);

    $task = Task::factory()->for($project)->for($reporter, 'creator')->create([
        'assignee_id' => $assignee->id,
        'status' => TaskStatus::Todo,
        'rank' => 1000,
    ]);
    $comment = TaskComment::factory()->for($task)->for($member)->create();
    $media = Media::factory()->for($member, 'uploader')->create();
    $attachment = TaskAttachment::factory()->for($task)->create(['media_id' => $media->id]);
    $foreignTask = Task::factory()->for($foreignProject)->for($foreignOwner, 'creator')->create();
    if ($status !== ProjectStatus::Active) {
        $project->update(['status' => $status]);
        $project->refresh();
        $task->load('project');
    }

    return compact(
        'admin',
        'owner',
        'memberManager',
        'globalManager',
        'reporter',
        'assignee',
        'member',
        'outsider',
        'foreignOwner',
        'project',
        'foreignProject',
        'task',
        'comment',
        'attachment',
        'foreignTask',
    );
}

test('ordinary global members receive broad capabilities whose record scope is decided by policies', function (): void {
    $member = User::factory()->asMember()->create();

    expect($member->hasAllPermissions([
        PermissionName::TasksView->value,
        PermissionName::TasksCreate->value,
        PermissionName::TasksUpdate->value,
        PermissionName::TasksAssign->value,
        PermissionName::TasksStatusChange->value,
        PermissionName::TasksDelete->value,
        PermissionName::CommentsCreate->value,
        PermissionName::CommentsDelete->value,
        PermissionName::AttachmentsUpload->value,
        PermissionName::AttachmentsDelete->value,
        PermissionName::ActivityView->value,
    ]))->toBeTrue();
});

test('task policy keeps global roles independent from project and actor context', function (): void {
    $context = contextualAuthorizationFixture();
    extract($context);
    $policy = app(TaskPolicy::class);

    foreach ([$admin, $owner, $memberManager, $globalManager] as $manager) {
        expect($policy->view($manager, $task))->toBeTrue()
            ->and($policy->create($manager, $project))->toBeTrue()
            ->and($policy->update($manager, $task))->toBeTrue()
            ->and($policy->delete($manager, $task))->toBeTrue()
            ->and($policy->assign($manager, $task, $member))->toBeTrue()
            ->and($policy->assign($manager, $task, null))->toBeTrue()
            ->and($policy->changeStatus($manager, $task))->toBeTrue()
            ->and($policy->comment($manager, $task))->toBeTrue()
            ->and($policy->watch($manager, $task, $member))->toBeTrue()
            ->and($policy->reorder($manager, $task))->toBeTrue()
            ->and($policy->deleteComment($manager, $task, $comment))->toBeTrue()
            ->and($policy->uploadAttachment($manager, $task))->toBeTrue()
            ->and($policy->deleteAttachment($manager, $task, $attachment))->toBeTrue();
    }

    expect($policy->view($member, $task))->toBeTrue()
        ->and($policy->create($member, $project))->toBeTrue()
        ->and($policy->update($reporter, $task))->toBeTrue()
        ->and($policy->update($member, $task))->toBeFalse()
        ->and($policy->assign($member, $task, $member))->toBeTrue()
        ->and($policy->assign($member, $task, $assignee))->toBeFalse()
        ->and($policy->changeStatus($assignee, $task))->toBeTrue()
        ->and($policy->changeStatus($member, $task))->toBeFalse()
        ->and($policy->delete($member, $task))->toBeFalse()
        ->and($policy->reorder($member, $task))->toBeFalse()
        ->and($policy->deleteComment($member, $task, $comment))->toBeTrue()
        ->and($policy->deleteAttachment($member, $task, $attachment))->toBeTrue()
        ->and($policy->view($outsider, $task))->toBeFalse()
        ->and($policy->create($outsider, $project))->toBeFalse()
        ->and($policy->update($memberManager, $foreignTask))->toBeFalse()
        ->and($policy->delete($memberManager, $foreignTask))->toBeFalse()
        ->and($policy->assign($memberManager, $foreignTask, $foreignOwner))->toBeFalse();
});

test('task policy permits reads but rejects every mutation for read-only projects', function (ProjectStatus $status): void {
    $context = contextualAuthorizationFixture($status);
    extract($context);
    $policy = app(TaskPolicy::class);

    expect($policy->view($memberManager, $task))->toBeTrue()
        ->and($policy->create($memberManager, $project))->toBeFalse()
        ->and($policy->update($memberManager, $task))->toBeFalse()
        ->and($policy->delete($memberManager, $task))->toBeFalse()
        ->and($policy->assign($memberManager, $task, $member))->toBeFalse()
        ->and($policy->changeStatus($memberManager, $task))->toBeFalse()
        ->and($policy->comment($memberManager, $task))->toBeFalse()
        ->and($policy->watch($memberManager, $task, $member))->toBeFalse()
        ->and($policy->reorder($memberManager, $task))->toBeFalse()
        ->and($policy->deleteComment($memberManager, $task, $comment))->toBeFalse()
        ->and($policy->uploadAttachment($memberManager, $task))->toBeFalse()
        ->and($policy->deleteAttachment($memberManager, $task, $attachment))->toBeFalse();
})->with([
    'completed' => ProjectStatus::Completed,
    'archived' => ProjectStatus::Archived,
]);

test('suspended actors fail task policies and direct service invariants', function (): void {
    $context = contextualAuthorizationFixture();
    extract($context);
    $memberManager->forceFill(['status' => AccountStatus::Suspended])->save();
    $memberManager = $memberManager->fresh();
    $policy = app(TaskPolicy::class);

    expect($policy->viewAny($memberManager))->toBeFalse()
        ->and($policy->view($memberManager, $task))->toBeFalse()
        ->and($policy->create($memberManager, $project))->toBeFalse()
        ->and($policy->update($memberManager, $task))->toBeFalse()
        ->and($policy->delete($memberManager, $task))->toBeFalse()
        ->and($policy->assign($memberManager, $task, $member))->toBeFalse()
        ->and($policy->changeStatus($memberManager, $task))->toBeFalse()
        ->and($policy->reorder($memberManager, $task))->toBeFalse()
        ->and(fn () => app(TaskService::class)->update(
            $task->load('project'),
            new UpdateTaskData('Blocked', null, TaskPriority::Low, null),
            $memberManager,
        ))->toThrow(LogicException::class)
        ->and(fn () => app(TaskAssignmentService::class)->assign($task->fresh()->load('project'), $member, $memberManager))
        ->toThrow(LogicException::class)
        ->and(fn () => app(TaskRankService::class)->reorder(
            $task->fresh()->load('project'),
            new ReorderTaskData(null, null, $task->version),
            $memberManager,
        ))->toThrow(LogicException::class);
});

test('direct mutation services enforce the same context-manager and ordinary-member boundaries', function (): void {
    Storage::fake('local');
    $context = contextualAuthorizationFixture();
    extract($context);
    $taskService = app(TaskService::class);

    $updated = $taskService->update(
        $task->load('project'),
        new UpdateTaskData('Context manager edit', null, TaskPriority::High, null),
        $memberManager,
    );
    expect($updated->title)->toBe('Context manager edit');

    $assignmentTask = Task::factory()->for($project)->for($reporter, 'creator')->create(['assignee_id' => null]);
    expect(app(TaskAssignmentService::class)->assign($assignmentTask->load('project'), $member, $memberManager)->assignee_id)
        ->toBe($member->id);

    $statusTask = Task::factory()->for($project)->for($reporter, 'creator')->create([
        'assignee_id' => null,
        'status' => TaskStatus::Todo,
    ]);
    expect(app(TaskStatusService::class)->change(
        $statusTask->load('project'),
        new ChangeTaskStatusData(TaskStatus::InProgress, $statusTask->version),
        $memberManager,
    )->status)->toBe(TaskStatus::InProgress);

    $rankTask = Task::factory()->for($project)->for($reporter, 'creator')->create(['rank' => 2000]);
    app(TaskRankService::class)->reorder(
        $rankTask->load('project'),
        new ReorderTaskData($task->id, null, $rankTask->version),
        $memberManager,
    );

    app(TaskCommentService::class)->delete($comment, $memberManager);
    expect(TaskComment::query()->find($comment->id))->toBeNull();

    $attachmentService = app(TaskAttachmentService::class);
    $uploaded = $attachmentService->upload(
        $task->fresh()->load('project'),
        $member,
        UploadedFile::fake()->createWithContent('context.txt', 'context'),
    );
    $attachmentService->delete($task, $uploaded, $memberManager);
    expect($uploaded->fresh())->toBeNull();

    $memberComment = TaskComment::factory()->for($task)->for($reporter)->create();
    $memberAttachment = $attachmentService->upload(
        $task->fresh()->load('project'),
        $reporter,
        UploadedFile::fake()->createWithContent('reporter.txt', 'reporter'),
    );
    $deletionTask = Task::factory()->for($project)->for($reporter, 'creator')->create();

    expect(fn () => $taskService->update(
        $foreignTask->load('project'),
        new UpdateTaskData('Foreign edit', null, TaskPriority::Low, null),
        $memberManager,
    ))->toThrow(LogicException::class)
        ->and(fn () => app(TaskAssignmentService::class)->assign($foreignTask->fresh()->load('project'), $foreignOwner, $memberManager))
        ->toThrow(LogicException::class)
        ->and(fn () => app(TaskStatusService::class)->change(
            $foreignTask->fresh()->load('project'),
            new ChangeTaskStatusData(TaskStatus::InProgress, $foreignTask->version),
            $memberManager,
        ))->toThrow(InvalidTaskStatusTransition::class)
        ->and(fn () => $taskService->delete($foreignTask->fresh()->load('project'), $memberManager))
        ->toThrow(LogicException::class)
        ->and(fn () => $taskService->update(
            $task->fresh()->load('project'),
            new UpdateTaskData('Member edit', null, TaskPriority::Low, null),
            $member,
        ))->toThrow(LogicException::class)
        ->and(fn () => app(TaskAssignmentService::class)->assign($assignmentTask->fresh()->load('project'), $reporter, $member))
        ->toThrow(LogicException::class)
        ->and(fn () => app(TaskStatusService::class)->change(
            $statusTask->fresh()->load('project'),
            new ChangeTaskStatusData(TaskStatus::Review, $statusTask->fresh()->version),
            $member,
        ))->toThrow(InvalidTaskStatusTransition::class)
        ->and(fn () => app(TaskRankService::class)->reorder(
            $rankTask->fresh()->load('project'),
            new ReorderTaskData(null, null, $rankTask->fresh()->version),
            $member,
        ))->toThrow(LogicException::class)
        ->and(fn () => app(TaskCommentService::class)->delete($memberComment, $member))
        ->toThrow(LogicException::class)
        ->and(fn () => $attachmentService->delete($task, $memberAttachment, $member))
        ->toThrow(LogicException::class)
        ->and(fn () => $taskService->delete($deletionTask->load('project'), $member))
        ->toThrow(LogicException::class);

    $taskService->delete($deletionTask->fresh()->load('project'), $memberManager);
    expect(Task::query()->find($deletionTask->id))->toBeNull();
});
