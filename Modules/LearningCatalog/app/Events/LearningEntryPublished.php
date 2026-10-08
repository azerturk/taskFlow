<?php

namespace Modules\LearningCatalog\Events;

use DateTimeImmutable;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

final readonly class LearningEntryPublished implements ShouldDispatchAfterCommit
{
    public function __construct(
        public string $eventId,
        public int $entryId,
        public string $title,
        public DateTimeImmutable $publishedAt,
    ) {}
}
