<?php

namespace Modules\Media\Exceptions;

use Modules\Media\Data\MediaMetadataData;
use Throwable;

class MediaBatchStorageException extends MediaStorageException
{
    /** @param array<int, MediaMetadataData> $storedItems */
    public function __construct(string $message, private readonly array $storedItems, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    /** @return array<int, MediaMetadataData> */
    public function storedItems(): array
    {
        return $this->storedItems;
    }
}
