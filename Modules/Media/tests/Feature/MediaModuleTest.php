<?php

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;
use Modules\Media\Data\MediaMetadataData;
use Modules\Media\Http\Resources\MediaResource;
use Modules\Media\Models\Media;
use Modules\Media\Repositories\Contracts\MediaRepositoryInterface;
use Modules\Media\Repositories\Eloquent\EloquentMediaRepository;
use Modules\Media\Services\MediaMetadataService;

it('boots the Media module, registers its migration and binds its repository', function (): void {
    expect(Schema::hasTable('media'))->toBeTrue()
        ->and(app(MediaRepositoryInterface::class))->toBeInstanceOf(EloquentMediaRepository::class);
});

it('contains no Task authorization or Task-module dependency in the scaffold', function (): void {
    $source = collect([
        module_path('Media', 'app/Models/Media.php'),
        module_path('Media', 'app/Repositories/Contracts/MediaRepositoryInterface.php'),
        module_path('Media', 'app/Repositories/Eloquent/EloquentMediaRepository.php'),
        module_path('Media', 'app/Services/MediaMetadataService.php'),
        module_path('Media', 'app/Services/MediaStorageService.php'),
        module_path('Media', 'app/Http/Resources/MediaResource.php'),
        module_path('Media', 'app/Providers/MediaServiceProvider.php'),
    ])
        ->map(fn (string $file): string => file_get_contents($file))
        ->implode("\n");

    expect($source)->not->toContain('Modules\\Tasks')
        ->not->toContain('authorize(')
        ->not->toContain('TaskPolicy');
});

it('persists central media metadata without accepting task authorization responsibility', function (): void {
    $uploader = User::factory()->create();
    $media = app(MediaMetadataService::class)->register($uploader, new MediaMetadataData(
        uuid: 'af15c7ad-2a5e-45ce-8603-f3470562c9d1',
        disk: 'local',
        path: 'media/af15c7ad-2a5e-45ce-8603-f3470562c9d1.pdf',
        originalName: 'quarterly-report.pdf',
        extension: 'pdf',
        mimeType: 'application/pdf',
        size: 2048,
        sha256: str_repeat('a', 64),
    ));

    expect($media->uploaded_by)->toBe($uploader->id)
        ->and($media->uuid)->toBe('af15c7ad-2a5e-45ce-8603-f3470562c9d1')
        ->and(app(MediaRepositoryInterface::class)->findByUuidOrFail($media->uuid)->is($media))->toBeTrue();
});

it('enforces unique public UUID and internal storage path constraints', function (): void {
    $media = Media::factory()->create();

    expect(fn () => Media::factory()->create(['uuid' => $media->uuid]))->toThrow(QueryException::class)
        ->and(fn () => Media::factory()->create(['path' => $media->path]))->toThrow(QueryException::class);
});

it('keeps disk, path and checksum out of the public media resource', function (): void {
    $media = Media::factory()->create([
        'disk' => 'private',
        'path' => 'media/private-secret.pdf',
        'sha256' => str_repeat('f', 64),
    ]);

    $resource = (new MediaResource($media))->resolve();

    expect($resource)->toHaveKeys(['id', 'uuid', 'original_name', 'extension', 'mime_type', 'size', 'image_width', 'image_height', 'created_at'])
        ->not->toHaveKeys(['disk', 'path', 'sha256'])
        ->and(json_encode($resource))->not->toContain('private-secret')
        ->and(json_encode($resource))->not->toContain(str_repeat('f', 64));
});
