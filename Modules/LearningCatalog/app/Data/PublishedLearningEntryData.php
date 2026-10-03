<?php

namespace Modules\LearningCatalog\Data;

use DateTimeImmutable;

final readonly class PublishedLearningEntryData
{
    public function __construct(
        public int $id,
        public string $title,
        public DateTimeImmutable $publishedAt,
    ) {}
}
