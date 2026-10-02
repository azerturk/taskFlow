<?php

namespace Modules\Media\Exceptions;

use Throwable;

class MediaCleanupPendingException extends MediaStorageException
{
    /** @param list<string> $mediaUuids */
    public function __construct(public readonly array $mediaUuids, ?Throwable $previous = null)
    {
        parent::__construct('One or more stored media files require cleanup retry.', 0, $previous);
    }
}
