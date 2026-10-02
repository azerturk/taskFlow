<?php

namespace Modules\Media\Repositories\Contracts;

use Modules\Media\Models\Media;

interface MediaRepositoryInterface
{
    public function save(Media $media): Media;

    public function findByUuidOrFail(string $uuid): Media;

    public function findByUuidIncludingTrashed(string $uuid): ?Media;

    public function delete(Media $media): void;

    public function restore(Media $media): Media;
}
