<?php

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Modules\Activity\Services\ActivityRecorder;
use Modules\Media\Exceptions\MediaBatchStorageException;
use Modules\Media\Exceptions\MediaCleanupPendingException;
use Modules\Media\Exceptions\MediaStorageException;
use Modules\Media\Models\Media;
use Modules\Media\Repositories\Contracts\MediaRepositoryInterface;
use Modules\Media\Repositories\Eloquent\EloquentMediaRepository;
use Modules\Media\Services\MediaMetadataService;
use Modules\Media\Services\MediaStorageService;
use Modules\Projects\Enums\ProjectMemberRole;
use Modules\Projects\Enums\ProjectStatus;
use Modules\Projects\Models\Project;
use Modules\Projects\Services\ProjectMemberService;
use Modules\Tasks\Models\Task;
use Modules\Tasks\Models\TaskAttachment;
use Modules\Tasks\Repositories\Contracts\TaskAttachmentRepositoryInterface;
use Modules\Tasks\Services\TaskAttachmentService;
use Spatie\Activitylog\Models\Activity;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

function attachmentFailureContext(): array
{
    $manager = User::factory()->asProjectManager()->create();
    $project = Project::factory()->active()->create(['owner_id' => $manager->id]);
    $task = Task::factory()->for($project)->create();

    return [$manager, $project, $task];
}

it('compensates the first stored file when physical storage fails at the second item', function (): void {
    [$manager, , $task] = attachmentFailureContext();
    $writes = 0;
    $disk = Mockery::mock();
    $disk->shouldReceive('putFileAs')->twice()->andReturnUsing(function (string $directory, mixed $file, string $name) use (&$writes): string {
        if (++$writes === 2) {
            throw new RuntimeException('injected second write failure');
        }

        return $directory.'/'.$name;
    });
    $disk->shouldReceive('exists')->once()->andReturnTrue();
    $disk->shouldReceive('delete')->once()->andReturnTrue();
    Storage::shouldReceive('disk')->with('local')->andReturn($disk);

    expect(fn () => app(TaskAttachmentService::class)->uploadMany($task, $manager, [
        UploadedFile::fake()->createWithContent('one.txt', 'one'),
        UploadedFile::fake()->createWithContent('two.txt', 'two'),
    ]))->toThrow(MediaBatchStorageException::class)
        ->and(TaskAttachment::count())->toBe(0)
        ->and(Media::count())->toBe(0);
});

it('rolls back every metadata row when the second metadata insert fails', function (): void {
    [$manager, , $task] = attachmentFailureContext();
    $delegate = app(EloquentMediaRepository::class);
    app()->instance(MediaRepositoryInterface::class, new class($delegate) implements MediaRepositoryInterface
    {
        private int $writes = 0;

        public function __construct(private readonly EloquentMediaRepository $delegate) {}

        public function save(Media $media): Media
        {
            if (++$this->writes === 2) {
                throw new RuntimeException('injected metadata failure');
            }

            return $this->delegate->save($media);
        }

        public function findByUuidOrFail(string $uuid): Media
        {
            return $this->delegate->findByUuidOrFail($uuid);
        }

        public function findByUuidIncludingTrashed(string $uuid): ?Media
        {
            return $this->delegate->findByUuidIncludingTrashed($uuid);
        }

        public function delete(Media $media): void
        {
            $this->delegate->delete($media);
        }

        public function restore(Media $media): Media
        {
            return $this->delegate->restore($media);
        }
    });
    app()->forgetInstance(MediaMetadataService::class);
    app()->forgetInstance(MediaStorageService::class);
    app()->forgetInstance(TaskAttachmentService::class);

    expect(fn () => app(TaskAttachmentService::class)->uploadMany($task, $manager, [
        UploadedFile::fake()->createWithContent('one.txt', 'one'),
        UploadedFile::fake()->createWithContent('two.txt', 'two'),
    ]))->toThrow(RuntimeException::class)
        ->and(TaskAttachment::count())->toBe(0)
        ->and(Media::count())->toBe(0)
        ->and(Activity::count())->toBe(0);
    Storage::disk('local')->assertDirectoryEmpty('media');
});

it('uses one top-level database transaction for metadata association and Activity work', function (): void {
    [$manager, , $task] = attachmentFailureContext();
    $baseline = DB::transactionLevel();
    $levels = [];
    $mediaDelegate = app(EloquentMediaRepository::class);
    app()->instance(MediaRepositoryInterface::class, new class($mediaDelegate, $levels) implements MediaRepositoryInterface
    {
        /** @param array<int, int> $levels */
        public function __construct(private readonly EloquentMediaRepository $delegate, private array &$levels) {}

        public function save(Media $media): Media
        {
            $this->levels[] = DB::transactionLevel();

            return $this->delegate->save($media);
        }

        public function findByUuidOrFail(string $uuid): Media
        {
            return $this->delegate->findByUuidOrFail($uuid);
        }

        public function findByUuidIncludingTrashed(string $uuid): ?Media
        {
            return $this->delegate->findByUuidIncludingTrashed($uuid);
        }

        public function delete(Media $media): void
        {
            $this->delegate->delete($media);
        }

        public function restore(Media $media): Media
        {
            return $this->delegate->restore($media);
        }
    });
    $attachments = Mockery::mock(TaskAttachmentRepositoryInterface::class);
    $attachments->shouldReceive('save')->twice()->andReturnUsing(function (TaskAttachment $attachment) use (&$levels): TaskAttachment {
        $levels[] = DB::transactionLevel();
        $attachment->save();

        return $attachment;
    });
    app()->instance(TaskAttachmentRepositoryInterface::class, $attachments);
    app()->forgetInstance(MediaMetadataService::class);
    app()->forgetInstance(MediaStorageService::class);
    app()->forgetInstance(TaskAttachmentService::class);

    app(TaskAttachmentService::class)->uploadMany($task, $manager, [
        UploadedFile::fake()->createWithContent('one.txt', 'one'),
        UploadedFile::fake()->createWithContent('two.txt', 'two'),
    ]);

    expect($levels)->toHaveCount(4)
        ->and(array_values(array_unique($levels)))->toBe([$baseline + 1])
        ->and(Media::count())->toBe(2)
        ->and(TaskAttachment::count())->toBe(2)
        ->and(Activity::count())->toBe(2);
});

it('rolls back metadata associations and activity when the second association insert fails', function (): void {
    [$manager, , $task] = attachmentFailureContext();
    $writes = 0;
    $repository = Mockery::mock(TaskAttachmentRepositoryInterface::class);
    $repository->shouldReceive('save')->twice()->andReturnUsing(function (TaskAttachment $attachment) use (&$writes): TaskAttachment {
        if (++$writes === 2) {
            throw new RuntimeException('injected association failure');
        }

        $attachment->save();

        return $attachment;
    });
    app()->instance(TaskAttachmentRepositoryInterface::class, $repository);
    app()->forgetInstance(TaskAttachmentService::class);

    expect(fn () => app(TaskAttachmentService::class)->uploadMany($task, $manager, [
        UploadedFile::fake()->createWithContent('one.txt', 'one'),
        UploadedFile::fake()->createWithContent('two.txt', 'two'),
    ]))->toThrow(RuntimeException::class)
        ->and(TaskAttachment::count())->toBe(0)
        ->and(Media::count())->toBe(0)
        ->and(Activity::count())->toBe(0);
    Storage::disk('local')->assertDirectoryEmpty('media');
});

it('rolls back metadata and association when Activity recording fails', function (): void {
    [$manager, , $task] = attachmentFailureContext();
    $activity = Mockery::mock(ActivityRecorder::class);
    $activity->shouldReceive('record')->once()->andThrow(new RuntimeException('injected Activity failure'));
    app()->instance(ActivityRecorder::class, $activity);
    app()->forgetInstance(TaskAttachmentService::class);

    expect(fn () => app(TaskAttachmentService::class)->upload(
        $task,
        $manager,
        UploadedFile::fake()->createWithContent('one.txt', 'one'),
    ))->toThrow(RuntimeException::class)
        ->and(TaskAttachment::count())->toBe(0)
        ->and(Media::count())->toBe(0);
    Storage::disk('local')->assertDirectoryEmpty('media');
});

it('retains an unassociated Media record and safe log when upload compensation cannot delete the file', function (): void {
    [$manager, , $task] = attachmentFailureContext();
    $activity = Mockery::mock(ActivityRecorder::class);
    $activity->shouldReceive('record')->once()->andThrow(new RuntimeException('injected Activity failure'));
    app()->instance(ActivityRecorder::class, $activity);

    $disk = Mockery::mock();
    $disk->shouldReceive('putFileAs')->once()->andReturnUsing(fn (string $directory, mixed $file, string $name): string => $directory.'/'.$name);
    $disk->shouldReceive('exists')->once()->andReturnTrue();
    $disk->shouldReceive('delete')->once()->andReturnFalse();
    Storage::shouldReceive('disk')->with('local')->andReturn($disk);
    Log::spy();
    app()->forgetInstance(TaskAttachmentService::class);

    $failure = null;
    try {
        app(TaskAttachmentService::class)->upload(
            $task,
            $manager,
            UploadedFile::fake()->createWithContent('one.txt', 'one'),
        );
    } catch (MediaCleanupPendingException $exception) {
        $failure = $exception;
    }

    expect($failure)->toBeInstanceOf(MediaCleanupPendingException::class)
        ->and($failure->mediaUuids)->toHaveCount(1)
        ->and(TaskAttachment::count())->toBe(0)
        ->and(Media::query()->where('uuid', $failure->mediaUuids[0])->exists())->toBeTrue();
    Log::shouldHaveReceived('warning')->withArgs(function (string $message, array $context): bool {
        return $message === 'Media upload compensation deferred.'
            && $context['record_retained'] === true
            && ! array_key_exists('path', $context)
            && ! array_key_exists('disk', $context)
            && ! array_key_exists('sha256', $context);
    })->once();
});

it('keeps deletion retryable when physical cleanup fails and soft deletes metadata only after retry succeeds', function (): void {
    [$manager, , $task] = attachmentFailureContext();
    $attachment = app(TaskAttachmentService::class)->upload(
        $task,
        $manager,
        UploadedFile::fake()->createWithContent('one.txt', 'one'),
    );
    $media = $attachment->media;
    $disk = Mockery::mock();
    $disk->shouldReceive('exists')->twice()->andReturnTrue();
    $disk->shouldReceive('delete')->twice()->andReturn(false, true);
    Storage::shouldReceive('disk')->with('local')->andReturn($disk);
    Log::spy();

    expect(fn () => app(TaskAttachmentService::class)->delete($task, $attachment, $manager))
        ->toThrow(MediaCleanupPendingException::class)
        ->and(TaskAttachment::query()->find($attachment->id))->toBeNull()
        ->and(Media::withTrashed()->find($media->id)?->trashed())->toBeFalse();

    app(MediaStorageService::class)->delete($media);
    expect(Media::withTrashed()->find($media->id)?->trashed())->toBeTrue();
    Log::shouldHaveReceived('warning')->withArgs(function (string $message, array $context): bool {
        return $message === 'Media physical deletion deferred.'
            && ! array_key_exists('path', $context)
            && ! array_key_exists('disk', $context)
            && ! array_key_exists('sha256', $context);
    })->once();
});

it('leaves observable metadata when metadata deletion fails after physical cleanup', function (): void {
    [$manager, , $task] = attachmentFailureContext();
    $attachment = app(TaskAttachmentService::class)->upload(
        $task,
        $manager,
        UploadedFile::fake()->createWithContent('one.txt', 'one'),
    );
    $media = $attachment->media;
    $delegate = app(EloquentMediaRepository::class);
    app()->instance(MediaRepositoryInterface::class, new class($delegate) implements MediaRepositoryInterface
    {
        public function __construct(private readonly EloquentMediaRepository $delegate) {}

        public function save(Media $media): Media
        {
            return $this->delegate->save($media);
        }

        public function findByUuidOrFail(string $uuid): Media
        {
            return $this->delegate->findByUuidOrFail($uuid);
        }

        public function findByUuidIncludingTrashed(string $uuid): ?Media
        {
            return $this->delegate->findByUuidIncludingTrashed($uuid);
        }

        public function delete(Media $media): void
        {
            throw new RuntimeException('injected metadata delete failure');
        }

        public function restore(Media $media): Media
        {
            return $this->delegate->restore($media);
        }
    });
    app()->forgetInstance(MediaMetadataService::class);
    app()->forgetInstance(MediaStorageService::class);
    app()->forgetInstance(TaskAttachmentService::class);

    expect(fn () => app(TaskAttachmentService::class)->delete($task, $attachment, $manager))
        ->toThrow(MediaStorageException::class)
        ->and(TaskAttachment::query()->find($attachment->id))->toBeNull()
        ->and(Media::withTrashed()->find($media->id)?->trashed())->toBeFalse();
    Storage::disk('local')->assertMissing($media->path);
});

it('enforces permission membership uploader manager and read-only rules on direct service calls', function (): void {
    [$manager, $project, $task] = attachmentFailureContext();
    $uploader = User::factory()->asMember()->create();
    $otherMember = User::factory()->asMember()->create();
    $outsider = User::factory()->asMember()->create();
    $permissionlessMember = User::factory()->create();
    $members = app(ProjectMemberService::class);
    foreach ([$uploader, $otherMember, $permissionlessMember] as $member) {
        $members->addMember($project, $member, ProjectMemberRole::Member, actor: $manager);
    }

    $service = app(TaskAttachmentService::class);
    $attachment = $service->upload(
        $task,
        $uploader,
        UploadedFile::fake()->createWithContent('mine.txt', 'mine'),
    );

    expect(fn () => $service->paginateFor($task, $outsider, 20))->toThrow(LogicException::class)
        ->and(fn () => $service->download($task, $attachment, $outsider))->toThrow(LogicException::class)
        ->and(fn () => $service->delete($task, $attachment, $otherMember))->toThrow(LogicException::class)
        ->and(fn () => $service->upload($task, $permissionlessMember, UploadedFile::fake()->createWithContent('no.txt', 'no')))
        ->toThrow(LogicException::class);

    $project->update(['status' => ProjectStatus::Completed]);
    expect($service->paginateFor($task, $uploader, 20))->toBeInstanceOf(LengthAwarePaginator::class)
        ->and(fn () => $service->delete($task, $attachment, $manager))->toThrow(LogicException::class)
        ->and(fn () => $service->upload($task, $uploader, UploadedFile::fake()->createWithContent('frozen.txt', 'frozen')))
        ->toThrow(LogicException::class);
});
