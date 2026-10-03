<?php

namespace Modules\LearningInsights\Listeners;

use Modules\LearningCatalog\Events\LearningEntryPublished;
use Modules\LearningInsights\Repositories\Contracts\LearningInsightRepositoryInterface;

class RecordPublishedLearningEntry
{
    public function __construct(private readonly LearningInsightRepositoryInterface $insights) {}

    public function handle(LearningEntryPublished $event): void
    {
        $this->insights->recordPublishedIfMissing(
            entryId: $event->entryId,
            sourceEventId: $event->eventId,
            title: $event->title,
            publishedAt: $event->publishedAt,
        );
    }
}
