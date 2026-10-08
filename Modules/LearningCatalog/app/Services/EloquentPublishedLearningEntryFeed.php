<?php

namespace Modules\LearningCatalog\Services;

use Modules\LearningCatalog\Contracts\PublishedLearningEntryFeed;
use Modules\LearningCatalog\Data\PublishedLearningEntryData;
use Modules\LearningCatalog\Models\LearningEntry;
use Modules\LearningCatalog\Repositories\Contracts\LearningEntryRepositoryInterface;

class EloquentPublishedLearningEntryFeed implements PublishedLearningEntryFeed
{
    public function __construct(private readonly LearningEntryRepositoryInterface $entries) {}

    public function all(): array
    {
        return array_map(
            static fn (LearningEntry $entry): PublishedLearningEntryData => new PublishedLearningEntryData(
                id: $entry->id,
                title: $entry->title,
                publishedAt: $entry->published_at->toDateTimeImmutable(),
            ),
            $this->entries->allPublished(),
        );
    }
}
