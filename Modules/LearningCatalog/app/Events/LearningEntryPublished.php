<?php

namespace Modules\LearningCatalog\Events;

use DateTimeImmutable;

final readonly class LearningEntryPublished
{
    public function __construct(
        public string $eventId,
        public int $entryId,
        public string $title,
        public DateTimeImmutable $publishedAt,
    ) {}
}
