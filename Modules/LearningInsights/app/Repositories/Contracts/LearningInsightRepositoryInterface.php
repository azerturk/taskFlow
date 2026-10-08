<?php

namespace Modules\LearningInsights\Repositories\Contracts;

use DateTimeImmutable;

interface LearningInsightRepositoryInterface
{
    public function recordPublishedIfMissing(int $entryId, ?string $sourceEventId, string $title, DateTimeImmutable $publishedAt): void;
}
