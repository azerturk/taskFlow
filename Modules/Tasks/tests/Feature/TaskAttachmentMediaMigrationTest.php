<?php

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Modules\Media\Models\Media;
use Modules\Projects\Models\Project;
use Modules\Tasks\Http\Resources\TaskAttachmentResource;
use Modules\Tasks\Models\Task;
use Modules\Tasks\Models\TaskAttachment;
use Modules\Tasks\Services\TaskAttachmentService;
use Modules\Tasks\Support\TaskAttachmentMediaBackfill;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

it('creates a single association with Media as the sole physical metadata owner', function (): void {
    $actor = User::factory()->asProjectManager()->create();
    $project = Project::factory()->active()->create(['owner_id' => $actor->id]);
    $task = Task::factory()->for($project)->create();

    $attachment = app(TaskAttachmentService::class)->upload(
        $task->load('project'),
        $actor,
        UploadedFile::fake()->createWithContent('evidence.txt', 'preserved task evidence'),
    );

    $attachment->load('media');
    expect($attachment->media)->toBeInstanceOf(Media::class)
        ->and($attachment->media_id)->toBe($attachment->media->id)
        ->and(Media::count())->toBe(1)
        ->and(TaskAttachment::count())->toBe(1);
    expect(Schema::hasColumns('task_attachments', ['disk', 'path', 'original_name', 'mime_type', 'size', 'uploaded_by']))->toBeFalse();
    Storage::disk('local')->assertExists($attachment->media->path);
});

it('keeps the authorized download backed by Media metadata without exposing internal storage fields', function (): void {
    $actor = User::factory()->asProjectManager()->create();
    $project = Project::factory()->active()->create(['owner_id' => $actor->id]);
    $task = Task::factory()->for($project)->create();
    $attachment = app(TaskAttachmentService::class)->upload(
        $task->load('project'),
        $actor,
        UploadedFile::fake()->createWithContent('evidence.txt', 'preserved task evidence'),
    );

    $resource = (new TaskAttachmentResource($attachment->load('media.uploader')))->resolve();
    $response = app(TaskAttachmentService::class)->download($task, $attachment, $actor);

    expect($resource)->toMatchArray([
        'media_uuid' => $attachment->media->uuid,
        'original_name' => 'evidence.txt',
        'mime_type' => 'text/plain',
        'size' => strlen('preserved task evidence'),
    ])->not->toHaveKeys(['disk', 'path', 'sha256'])
        ->and(json_encode($resource))->not->toContain($attachment->media->path)
        ->and($response->getStatusCode())->toBe(200)
        ->and($response->headers->get('content-disposition'))->toContain('attachment');
});

it('removes the association and its Media record/file together', function (): void {
    $actor = User::factory()->asProjectManager()->create();
    $project = Project::factory()->active()->create(['owner_id' => $actor->id]);
    $task = Task::factory()->for($project)->create();
    $attachment = app(TaskAttachmentService::class)->upload(
        $task->load('project'),
        $actor,
        UploadedFile::fake()->createWithContent('evidence.txt', 'preserved task evidence'),
    );
    $media = $attachment->media;

    app(TaskAttachmentService::class)->delete($task, $attachment, $actor);

    expect(TaskAttachment::count())->toBe(0)
        ->and(Media::withTrashed()->find($media->id)?->trashed())->toBeTrue();
    Storage::disk('local')->assertMissing($media->path);
});

it('rolls the ownership migration back and backfills preserved legacy data without moving its file', function (): void {
    $actor = User::factory()->asProjectManager()->create();
    $project = Project::factory()->active()->create(['owner_id' => $actor->id]);
    $task = Task::factory()->for($project)->create();
    $path = 'task-attachments/legacy-proof.txt';
    Storage::disk('local')->put($path, 'legacy evidence');
    $migration = require module_path('Tasks', 'database/migrations/2026_09_07_110000_finalize_task_attachment_media_ownership.php');
    $migration->down();

    $legacyId = DB::table('task_attachments')->insertGetId([
        'task_id' => $task->id,
        'uploaded_by' => $actor->id,
        'disk' => 'local',
        'path' => $path,
        'original_name' => 'legacy-proof.txt',
        'mime_type' => 'text/plain',
        'size' => strlen('legacy evidence'),
        'media_id' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $before = TaskAttachmentMediaBackfill::preflight();
    $migration->up();
    $legacy = TaskAttachment::query()->with('media')->findOrFail($legacyId);

    expect($before['null_media_associations'])->toBe(1)
        ->and(TaskAttachment::count())->toBe(1)
        ->and(Media::count())->toBe(1)
        ->and($legacy->media)->toBeInstanceOf(Media::class)
        ->and($legacy->media->path)->toBe($path)
        ->and($legacy->media->sha256)->toBe(hash('sha256', 'legacy evidence'))
        ->and(app(TaskAttachmentService::class)->download($task, $legacy, $actor)->getStatusCode())->toBe(200)
        ->and(Schema::hasColumn('task_attachments', 'path'))->toBeFalse();
    Storage::disk('local')->assertExists($path);
});

it('enforces required unique Media ownership at the database boundary', function (): void {
    $actor = User::factory()->create();
    $project = Project::factory()->active()->create(['owner_id' => $actor->id]);
    $task = Task::factory()->for($project)->create();
    $media = Media::factory()->for($actor, 'uploader')->create();

    expect(fn () => DB::table('task_attachments')->insert([
        'task_id' => $task->id,
        'media_id' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);

    TaskAttachment::factory()->for($task)->create(['media_id' => $media->id]);
    expect(fn () => TaskAttachment::factory()->for($task)->create(['media_id' => $media->id]))
        ->toThrow(QueryException::class);
});

it('reports preserved-data anomalies without replacing authoritative Media metadata', function (): void {
    $actor = User::factory()->create();
    $other = User::factory()->create();
    $project = Project::factory()->active()->create(['owner_id' => $actor->id]);
    $task = Task::factory()->for($project)->create();
    $media = Media::factory()->for($actor, 'uploader')->create(['size' => 2048]);
    $attachment = TaskAttachment::factory()->for($task)->create(['media_id' => $media->id]);
    $migration = require module_path('Tasks', 'database/migrations/2026_09_07_110000_finalize_task_attachment_media_ownership.php');
    $migration->down();

    DB::table('task_attachments')->where('id', $attachment->id)->update([
        'uploaded_by' => $other->id,
        'size' => 4096,
    ]);
    $report = TaskAttachmentMediaBackfill::preflight();

    expect($report)->toMatchArray([
        'attachment_count' => 1,
        'media_count' => 1,
        'uploader_mismatches' => 1,
        'size_mismatches' => 1,
        'unavailable_files' => 1,
    ]);

    $migration->up();
    expect($media->fresh()->uploaded_by)->toBe($actor->id)
        ->and($media->fresh()->size)->toBe(2048)
        ->and($attachment->fresh()->media_id)->toBe($media->id);
});
