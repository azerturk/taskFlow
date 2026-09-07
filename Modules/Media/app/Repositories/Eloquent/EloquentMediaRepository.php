<?php

namespace Modules\Media\Repositories\Eloquent;

use Modules\Media\Models\Media;
use Modules\Media\Repositories\Contracts\MediaRepositoryInterface;

class EloquentMediaRepository implements MediaRepositoryInterface
{
    public function save(Media $media): Media
    {
        $media->save();

        return $media;
    }

    public function findByUuidOrFail(string $uuid): Media
    {
        return Media::query()->where('uuid', $uuid)->firstOrFail();
    }

    public function findByUuidIncludingTrashed(string $uuid): ?Media
    {
        return Media::withTrashed()->where('uuid', $uuid)->first();
    }

    public function delete(Media $media): void
    {
        $media->delete();
    }

    public function restore(Media $media): Media
    {
        $media->restore();

        return $media;
    }
}
