<?php

namespace Modules\LearningInsights\Services;

use Illuminate\Support\Facades\DB;
use Modules\LearningCatalog\Contracts\PublishedLearningEntryFeed;
use Modules\LearningCatalog\Data\PublishedLearningEntryData;
use Modules\LearningInsights\Repositories\Contracts\LearningInsightRepositoryInterface;

class RebuildLearningInsightsService
{
    public function __construct(
        private readonly PublishedLearningEntryFeed $entries,
        private readonly LearningInsightRepositoryInterface $insights,
    ) {}

    public function rebuild(): void
    {
        $entries = $this->entries->all();

        DB::transaction(function () use ($entries): void {
            foreach ($entries as $entry) {
                $this->recordMissingProjection($entry);
            }
        });
    }

    private function recordMissingProjection(PublishedLearningEntryData $entry): void
    {
        $this->insights->recordPublishedIfMissing(
            entryId: $entry->id,
            sourceEventId: null,
            title: $entry->title,
            publishedAt: $entry->publishedAt,
        );
    }
}
