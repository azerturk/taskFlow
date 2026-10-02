<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Media\Exceptions\MediaStorageException;
use Modules\Media\Exceptions\MediaUploadValidationException;
use Modules\Media\Models\Media;
use Modules\Media\Repositories\Contracts\MediaRepositoryInterface;
use Modules\Media\Services\MediaMetadataService;
use Modules\Media\Services\MediaStorageService;

function mediaStorageService(): MediaStorageService
{
    return app(MediaStorageService::class);
}

it('stores a server-detected allowed file under a randomized private path with checksum metadata', function (): void {
    $uploader = User::factory()->create();
    $media = mediaStorageService()->store($uploader, UploadedFile::fake()->createWithContent('notes.txt', 'TaskFlow private text'));

    expect($media->disk)->toBe('local')
        ->and($media->path)->toMatch('/^media\/[0-9a-f-]{36}\.txt$/')
        ->and($media->original_name)->toBe('notes.txt')
        ->and($media->mime_type)->toBe('text/plain')
        ->and($media->sha256)->toBe(hash('sha256', 'TaskFlow private text'));
    Storage::disk('local')->assertExists($media->path);
});

it('rejects spoofed, unknown and oversized upload inputs before writing any file', function (): void {
    $uploader = User::factory()->create();
    $spoofed = UploadedFile::fake()->createWithContent('invoice.pdf', 'not a PDF');
    $unknown = UploadedFile::fake()->createWithContent('payload.svg', '<svg/>');
    $oversized = UploadedFile::fake()->create('too-large.txt', 10 * 1024 + 1, 'text/plain');

    expect(fn () => mediaStorageService()->store($uploader, $spoofed))->toThrow(MediaUploadValidationException::class)
        ->and(fn () => mediaStorageService()->store($uploader, $unknown))->toThrow(MediaUploadValidationException::class)
        ->and(fn () => mediaStorageService()->store($uploader, $oversized))->toThrow(MediaUploadValidationException::class)
        ->and(Media::count())->toBe(0);
    Storage::disk('local')->assertDirectoryEmpty('media');
});

it('validates the complete batch including count and image dimensions before persistence', function (): void {
    $uploader = User::factory()->create();
    $files = array_fill(0, 6, UploadedFile::fake()->createWithContent('note.txt', 'safe text'));

    expect(fn () => mediaStorageService()->storeMany($uploader, $files))->toThrow(MediaUploadValidationException::class)
        ->and(fn () => mediaStorageService()->store($uploader, UploadedFile::fake()->image('large.png', 8001, 1)))->toThrow(MediaUploadValidationException::class)
        ->and(Media::count())->toBe(0);
    Storage::disk('local')->assertDirectoryEmpty('media');
});

it('compensates physical files when metadata persistence fails', function (): void {
    $uploader = User::factory()->create();
    app()->instance(MediaRepositoryInterface::class, new class implements MediaRepositoryInterface
    {
        public function save(Media $media): Media
        {
            throw new RuntimeException('database write failed');
        }

        public function findByUuidOrFail(string $uuid): Media
        {
            throw new RuntimeException('not used');
        }

        public function findByUuidIncludingTrashed(string $uuid): ?Media
        {
            return null;
        }

        public function delete(Media $media): void
        {
            throw new RuntimeException('not used');
        }

        public function restore(Media $media): Media
        {
            throw new RuntimeException('not used');
        }
    });
    app()->forgetInstance(MediaMetadataService::class);
    app()->forgetInstance(MediaStorageService::class);

    expect(fn () => mediaStorageService()->store($uploader, UploadedFile::fake()->createWithContent('note.txt', 'safe text')))
        ->toThrow(MediaStorageException::class)
        ->and(Media::count())->toBe(0);
    Storage::disk('local')->assertDirectoryEmpty('media');
});

it('does not create metadata when private storage itself is unavailable', function (): void {
    $uploader = User::factory()->create();
    config()->set('media.disk', 'missing-private-disk');

    expect(fn () => mediaStorageService()->store($uploader, UploadedFile::fake()->createWithContent('note.txt', 'safe text')))
        ->toThrow(MediaStorageException::class)
        ->and(Media::count())->toBe(0);
});

it('streams only complete safe responses and fails safely when a private file is missing', function (): void {
    $uploader = User::factory()->create();
    $media = mediaStorageService()->store($uploader, UploadedFile::fake()->createWithContent("../../report\r\nX-Evil: yes.txt", 'safe text'));

    $download = mediaStorageService()->download($media);
    expect($download->headers->get('content-type'))->toContain('text/plain')
        ->and($download->headers->get('x-content-type-options'))->toBe('nosniff')
        ->and($download->headers->get('cache-control'))->toContain('private')
        ->and($download->headers->get('content-disposition'))->toContain('attachment')
        ->and($download->headers->get('content-disposition'))->not->toContain("\r")
        ->and($download->headers->get('content-disposition'))->not->toContain('X-Evil')
        ->and($download->getStatusCode())->toBe(200);

    Storage::disk('local')->delete($media->path);
    expect(fn () => mediaStorageService()->download($media))->toThrow(MediaStorageException::class);
});

it('uses inline preview only for image and PDF media, and cleans physical storage after metadata deletion', function (): void {
    $uploader = User::factory()->create();
    $image = mediaStorageService()->store($uploader, UploadedFile::fake()->image('preview.png', 30, 20));
    $text = mediaStorageService()->store($uploader, UploadedFile::fake()->createWithContent('notes.txt', 'safe text'));

    expect(mediaStorageService()->preview($image)->headers->get('content-disposition'))->toContain('inline')
        ->and(mediaStorageService()->preview($text)->headers->get('content-disposition'))->toContain('attachment');

    $path = $text->path;
    mediaStorageService()->delete($text);

    expect(Media::withTrashed()->find($text->id)?->trashed())->toBeTrue();
    Storage::disk('local')->assertMissing($path);
});
