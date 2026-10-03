<?php

namespace Modules\LearningInsights\Repositories\Eloquent;

use DateTimeImmutable;
use Modules\LearningInsights\Models\LearningEntryInsight;
use Modules\LearningInsights\Repositories\Contracts\LearningInsightRepositoryInterface;

class EloquentLearningInsightRepository implements LearningInsightRepositoryInterface
{
    public function recordPublishedIfMissing(int $entryId, ?string $sourceEventId, string $title, DateTimeImmutable $publishedAt): void
    {
        LearningEntryInsight::query()->firstOrCreate(
            ['entry_id' => $entryId],
            [
                'source_event_id' => $sourceEventId,
                'title' => $title,
                'published_at' => $publishedAt,
            ],
        );
    }
}
