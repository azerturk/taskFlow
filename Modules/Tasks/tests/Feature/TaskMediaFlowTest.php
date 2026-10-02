<?php

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Modules\Media\Models\Media;
use Modules\Projects\Enums\ProjectMemberRole;
use Modules\Projects\Enums\ProjectStatus;
use Modules\Projects\Models\Project;
use Modules\Projects\Services\ProjectMemberService;
use Modules\Tasks\Models\Task;
use Modules\Tasks\Models\TaskAttachment;
use Modules\Tasks\Services\TaskAttachmentService;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

function taskMediaContext(ProjectStatus $status = ProjectStatus::Active): array
{
    $manager = User::factory()->asProjectManager()->create();
    $project = Project::factory()->create(['owner_id' => $manager->id, 'status' => $status]);
    $task = Task::factory()->for($project)->create();

    return [$manager, $project, $task];
}

function taskMediaToken(User $user, array $abilities = ['tasks:read', 'tasks:write']): void
{
    Sanctum::actingAs($user, $abilities);
}

it('creates up to five Media associations through the target API and lists safe resources', function (): void {
    [$manager, $project, $task] = taskMediaContext();
    taskMediaToken($manager);

    $response = $this->postJson('/api/v1/tasks/'.$task->id.'/media', [
        'media' => [
            UploadedFile::fake()->createWithContent('one.txt', 'one'),
            UploadedFile::fake()->createWithContent('two.txt', 'two'),
            UploadedFile::fake()->createWithContent('three.txt', 'three'),
            UploadedFile::fake()->createWithContent('four.txt', 'four'),
            UploadedFile::fake()->createWithContent('five.txt', 'five'),
        ],
    ]);

    $response->assertCreated()->assertJsonCount(5, 'data')
        ->assertJsonMissingPath('data.0.disk')
        ->assertJsonMissingPath('data.0.path')
        ->assertJsonMissingPath('data.0.sha256');
    expect(TaskAttachment::count())->toBe(5)->and(Media::count())->toBe(5);

    $this->getJson('/api/v1/tasks/'.$task->id.'/media')->assertOk()->assertJsonCount(5, 'data');
});

it('compensates every Media record and file when one file in a batch is invalid', function (): void {
    [$manager, $project, $task] = taskMediaContext();
    taskMediaToken($manager);

    $this->postJson('/api/v1/tasks/'.$task->id.'/media', [
        'media' => [
            UploadedFile::fake()->createWithContent('good.txt', 'safe content'),
            UploadedFile::fake()->createWithContent('blocked.svg', '<svg/>'),
        ],
    ])->assertUnprocessable()->assertJsonValidationErrors('media');

    expect(TaskAttachment::count())->toBe(0)->and(Media::count())->toBe(0);
    Storage::disk('local')->assertDirectoryEmpty('media');
});

it('returns 404 for a Media association addressed below another task', function (): void {
    [$manager, $project, $task] = taskMediaContext();
    $otherTask = Task::factory()->for($project)->create();
    $attachment = app(TaskAttachmentService::class)->upload(
        $task->load('project'), $manager, UploadedFile::fake()->createWithContent('proof.txt', 'proof'),
    );
    taskMediaToken($manager);

    expect(fn () => app(TaskAttachmentService::class)->download($otherTask, $attachment, $manager))
        ->toThrow(ModelNotFoundException::class);
    $this->getJson('/api/v1/tasks/'.$otherTask->id.'/media/'.$attachment->id.'/download')->assertNotFound();
    $this->deleteJson('/api/v1/tasks/'.$otherTask->id.'/media/'.$attachment->id)->assertNotFound();
});

it('enforces member visibility, uploader-or-manager deletion, and active-project mutability', function (ProjectStatus $readOnlyStatus): void {
    [$manager, $project, $task] = taskMediaContext();
    $member = User::factory()->asMember()->create();
    $outsider = User::factory()->asMember()->create();
    app(ProjectMemberService::class)->addMember($project, $member, ProjectMemberRole::Member, actor: $manager);
    $attachment = app(TaskAttachmentService::class)->upload(
        $task->load('project'), $member, UploadedFile::fake()->createWithContent('mine.txt', 'mine'),
    );

    taskMediaToken($outsider);
    $this->getJson('/api/v1/tasks/'.$task->id.'/media')->assertNotFound();

    taskMediaToken($member);
    $this->deleteJson('/api/v1/tasks/'.$task->id.'/media/'.$attachment->id)->assertNoContent();

    $attachment = app(TaskAttachmentService::class)->upload(
        $task->load('project'), $member, UploadedFile::fake()->createWithContent('manager.txt', 'manager'),
    );
    taskMediaToken($manager);
    $this->deleteJson('/api/v1/tasks/'.$task->id.'/media/'.$attachment->id)->assertNoContent();

    $project->update(['status' => $readOnlyStatus]);
    taskMediaToken($member);
    $this->postJson('/api/v1/tasks/'.$task->id.'/media', [
        'media' => [UploadedFile::fake()->createWithContent('blocked.txt', 'blocked')],
    ])->assertForbidden();
})->with([[ProjectStatus::Completed], [ProjectStatus::Archived]]);

it('streams preview and download through the parent Task authorization boundary', function (): void {
    [$manager, $project, $task] = taskMediaContext();
    $attachment = app(TaskAttachmentService::class)->upload(
        $task->load('project'), $manager, UploadedFile::fake()->image('proof.png', 30, 20),
    );
    taskMediaToken($manager);

    $this->get('/api/v1/tasks/'.$task->id.'/media/'.$attachment->id.'/preview')
        ->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff');
    $download = $this->get('/api/v1/tasks/'.$task->id.'/media/'.$attachment->id.'/download');
    expect($download->headers->get('content-disposition'))->toContain('attachment')
        ->and($download->streamedContent())->toBe(Storage::disk($attachment->media->disk)->get($attachment->media->path));
});
