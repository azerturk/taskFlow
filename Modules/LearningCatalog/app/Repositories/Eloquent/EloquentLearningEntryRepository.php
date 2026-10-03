<?php

namespace Modules\LearningCatalog\Repositories\Eloquent;

use Modules\LearningCatalog\Data\PublishLearningEntryData;
use Modules\LearningCatalog\Models\LearningEntry;
use Modules\LearningCatalog\Repositories\Contracts\LearningEntryRepositoryInterface;

class EloquentLearningEntryRepository implements LearningEntryRepositoryInterface
{
    public function createPublished(PublishLearningEntryData $data): LearningEntry
    {
        return LearningEntry::query()->create([
            'title' => $data->title,
            'published_at' => now(),
        ]);
    }

    public function allPublished(): array
    {
        return LearningEntry::query()
            ->orderBy('id')
            ->get()
            ->all();
    }
}
