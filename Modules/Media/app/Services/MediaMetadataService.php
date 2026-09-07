<?php

namespace Modules\Media\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Media\Data\MediaMetadataData;
use Modules\Media\Models\Media;
use Modules\Media\Repositories\Contracts\MediaRepositoryInterface;

class MediaMetadataService
{
    public function __construct(private readonly MediaRepositoryInterface $media) {}

    /** Persists metadata produced by the trusted storage pipeline. */
    public function register(User $uploader, MediaMetadataData $data): Media
    {
        return DB::transaction(fn (): Media => $this->registerWithinTransaction($uploader, $data));
    }

    /** Transaction-neutral metadata registration for a top-level use case. */
    public function registerWithinTransaction(User $uploader, MediaMetadataData $data): Media
    {
        return $this->media->save(new Media([
            'uuid' => $data->uuid,
            'uploaded_by' => $uploader->id,
            'disk' => $data->disk,
            'path' => $data->path,
            'original_name' => $data->originalName,
            'extension' => $data->extension,
            'mime_type' => $data->mimeType,
            'size' => $data->size,
            'sha256' => $data->sha256,
            'image_width' => $data->imageWidth,
            'image_height' => $data->imageHeight,
        ]));
    }

    /**
     * @param  array<int, MediaMetadataData>  $items
     * @return array<int, Media>
     */
    public function registerMany(User $uploader, array $items): array
    {
        return DB::transaction(fn (): array => $this->registerManyWithinTransaction($uploader, $items));
    }

    /**
     * @param  array<int, MediaMetadataData>  $items
     * @return array<int, Media>
     */
    public function registerManyWithinTransaction(User $uploader, array $items): array
    {
        return array_map(
            fn (MediaMetadataData $item): Media => $this->registerWithinTransaction($uploader, $item),
            $items,
        );
    }

    public function retainForCleanup(User $uploader, MediaMetadataData $data): Media
    {
        return DB::transaction(function () use ($uploader, $data): Media {
            $existing = $this->media->findByUuidIncludingTrashed($data->uuid);
            if ($existing !== null) {
                if ($existing->trashed()) {
                    $existing = $this->media->restore($existing);
                }

                return $existing;
            }

            return $this->registerWithinTransaction($uploader, $data);
        });
    }

    public function delete(Media $media): void
    {
        DB::transaction(fn (): mixed => $this->deleteWithinTransaction($media));
    }

    /** Transaction-neutral metadata deletion for a top-level use case. */
    public function deleteWithinTransaction(Media $media): void
    {
        $this->media->delete($media);
    }
}
