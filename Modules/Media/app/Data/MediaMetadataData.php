<?php

namespace Modules\Media\Data;

readonly class MediaMetadataData
{
    public function __construct(
        public string $uuid,
        public string $disk,
        public string $path,
        public string $originalName,
        public string $extension,
        public string $mimeType,
        public int $size,
        public string $sha256,
        public ?int $imageWidth = null,
        public ?int $imageHeight = null,
    ) {}
}
