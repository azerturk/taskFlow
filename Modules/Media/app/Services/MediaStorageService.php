<?php

namespace Modules\Media\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Media\Data\MediaMetadataData;
use Modules\Media\Exceptions\MediaBatchStorageException;
use Modules\Media\Exceptions\MediaCleanupPendingException;
use Modules\Media\Exceptions\MediaStorageException;
use Modules\Media\Exceptions\MediaUploadValidationException;
use Modules\Media\Models\Media;
use Modules\Media\Support\MediaFilePolicy;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class MediaStorageService
{
    public function __construct(private readonly MediaMetadataService $metadata) {}

    /** @param array<int, UploadedFile> $files @return array<int, MediaMetadataData> */
    public function storeFiles(array $files): array
    {
        if ($files === [] || count($files) > config('media.max_files')) {
            throw new MediaUploadValidationException('The number of uploaded files is not allowed.');
        }

        $prepared = array_map(fn (UploadedFile $file): array => $this->prepare($file), $files);
        $disk = (string) config('media.disk');
        $metadata = [];

        foreach ($prepared as $item) {
            $uuid = (string) Str::uuid();
            $path = 'media/'.$uuid.'.'.$item['extension'];

            try {
                $stored = Storage::disk($disk)->putFileAs('media', $item['file'], basename($path));
                if ($stored === false) {
                    throw new MediaStorageException('The uploaded file could not be stored.');
                }
            } catch (Throwable $exception) {
                throw new MediaBatchStorageException(
                    'The uploaded file could not be stored.',
                    $metadata,
                    $exception,
                );
            }

            $metadata[] = new MediaMetadataData(
                uuid: $uuid,
                disk: $disk,
                path: $path,
                originalName: $item['original_name'],
                extension: $item['extension'],
                mimeType: $item['mime_type'],
                size: $item['size'],
                sha256: $item['sha256'],
                imageWidth: $item['image_width'],
                imageHeight: $item['image_height'],
            );
        }

        return $metadata;
    }

    /** @param array<int, UploadedFile> $files @return array<int, Media> */
    public function storeMany(User $uploader, array $files): array
    {
        try {
            $stored = $this->storeFiles($files);
        } catch (MediaBatchStorageException $exception) {
            $this->compensateStored($uploader, $exception->storedItems(), $exception);
            throw $exception;
        }

        try {
            return DB::transaction(fn (): array => $this->metadata->registerManyWithinTransaction($uploader, $stored));
        } catch (Throwable $exception) {
            $this->compensateStored($uploader, $stored, $exception);
            throw new MediaStorageException('The uploaded file metadata could not be saved.', previous: $exception);
        }
    }

    public function store(User $uploader, UploadedFile $file): Media
    {
        return $this->storeMany($uploader, [$file])[0];
    }

    public function delete(Media $media): void
    {
        try {
            $this->deletePhysical($media->disk, $media->path);
        } catch (Throwable $exception) {
            Log::warning('Media physical deletion deferred.', [
                'operation' => 'delete',
                'media_id' => $media->id,
                'media_uuid' => $media->uuid,
                'uploader_id' => $media->uploaded_by,
                'error_class' => $exception::class,
            ]);

            throw new MediaCleanupPendingException([$media->uuid], $exception);
        }

        try {
            $this->metadata->delete($media);
        } catch (Throwable $exception) {
            Log::error('Media metadata deletion failed after physical cleanup.', [
                'operation' => 'delete_metadata',
                'media_id' => $media->id,
                'media_uuid' => $media->uuid,
                'uploader_id' => $media->uploaded_by,
                'error_class' => $exception::class,
            ]);

            throw new MediaStorageException('The media metadata could not be deleted.', previous: $exception);
        }
    }

    /**
     * @param  array<int, MediaMetadataData>  $stored
     *
     * @throws MediaCleanupPendingException
     */
    public function compensateStored(User $uploader, array $stored, Throwable $cause): void
    {
        $pending = [];

        foreach (array_reverse($stored) as $item) {
            try {
                $this->deletePhysical($item->disk, $item->path);
            } catch (Throwable $cleanupFailure) {
                $recordRetained = false;
                try {
                    $this->metadata->retainForCleanup($uploader, $item);
                    $recordRetained = true;
                } catch (Throwable $recordFailure) {
                    Log::critical('Media cleanup record persistence failed.', [
                        'operation' => 'upload_compensation_record',
                        'media_uuid' => $item->uuid,
                        'uploader_id' => $uploader->id,
                        'error_class' => $recordFailure::class,
                    ]);
                }

                Log::warning('Media upload compensation deferred.', [
                    'operation' => 'upload_compensation',
                    'media_uuid' => $item->uuid,
                    'uploader_id' => $uploader->id,
                    'record_retained' => $recordRetained,
                    'error_class' => $cleanupFailure::class,
                ]);
                $pending[] = $item->uuid;
            }
        }

        if ($pending !== []) {
            throw new MediaCleanupPendingException($pending, $cause);
        }
    }

    public function download(Media $media): StreamedResponse
    {
        return $this->stream($media, false);
    }

    public function preview(Media $media): StreamedResponse
    {
        if (! MediaFilePolicy::isPreviewable($media->mime_type)) {
            return $this->download($media);
        }

        return $this->stream($media, true);
    }

    private function stream(Media $media, bool $inline): StreamedResponse
    {
        $disk = Storage::disk($media->disk);

        if (! $disk->exists($media->path)) {
            throw new MediaStorageException('The stored media file is unavailable.');
        }

        return $disk->response($media->path, MediaFilePolicy::safeOriginalName($media->original_name, $media->extension), [
            'Content-Type' => $media->mime_type,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ], $inline ? 'inline' : 'attachment');
    }

    private function deletePhysical(string $diskName, string $path): void
    {
        $disk = Storage::disk($diskName);
        if (! $disk->exists($path)) {
            return;
        }

        if (! $disk->delete($path)) {
            throw new MediaStorageException('The stored media file could not be deleted.');
        }
    }

    /** @return array{file: UploadedFile, extension: string, mime_type: string, original_name: string, size: int, sha256: string, image_width: ?int, image_height: ?int} */
    private function prepare(UploadedFile $file): array
    {
        if (! $file->isValid()) {
            throw new MediaUploadValidationException('The uploaded file is invalid.');
        }

        $extension = strtolower((string) pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION));
        $mimeType = $this->detectedMimeType($file);
        $size = (int) $file->getSize();

        if (! MediaFilePolicy::accepts($extension, $mimeType)) {
            throw new MediaUploadValidationException('The file type is not allowed.');
        }

        if ($size < 1 || $size > config('media.max_file_size')) {
            throw new MediaUploadValidationException('The file size is not allowed.');
        }

        [$width, $height] = $this->imageDimensions($file, $mimeType);
        $path = $file->getRealPath();

        if ($path === false || ! is_file($path)) {
            throw new MediaUploadValidationException('The uploaded file is unavailable.');
        }
        $checksum = hash_file('sha256', $path);
        if (! is_string($checksum)) {
            throw new MediaUploadValidationException('The uploaded file checksum could not be calculated.');
        }

        return [
            'file' => $file,
            'extension' => $extension,
            'mime_type' => $mimeType,
            'original_name' => MediaFilePolicy::safeOriginalName($file->getClientOriginalName(), $extension),
            'size' => $size,
            'sha256' => $checksum,
            'image_width' => $width,
            'image_height' => $height,
        ];
    }

    /** @return array{?int, ?int} */
    private function imageDimensions(UploadedFile $file, string $mimeType): array
    {
        if (! str_starts_with($mimeType, 'image/')) {
            return [null, null];
        }

        $dimensions = @getimagesize((string) $file->getRealPath());
        $width = $dimensions[0] ?? null;
        $height = $dimensions[1] ?? null;

        if (! is_int($width) || ! is_int($height)
            || $width < 1 || $height < 1
            || $width > config('media.max_image_width')
            || $height > config('media.max_image_height')
            || $width * $height > config('media.max_image_pixels')) {
            throw new MediaUploadValidationException('The image dimensions are not allowed.');
        }

        return [$width, $height];
    }

    private function detectedMimeType(UploadedFile $file): string
    {
        $path = $file->getRealPath();

        if ($path === false || ! is_file($path)) {
            throw new MediaUploadValidationException('The uploaded file is unavailable.');
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = $finfo === false ? false : finfo_file($finfo, $path);

        if ($finfo !== false) {
            finfo_close($finfo);
        }

        if (! is_string($mimeType) || $mimeType === '') {
            throw new MediaUploadValidationException('The uploaded file type could not be detected.');
        }

        return $mimeType;
    }
}
