<?php

namespace Modules\LearningCatalog\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\LearningCatalog\Data\PublishLearningEntryData;
use Modules\LearningCatalog\Events\LearningEntryPublished;
use Modules\LearningCatalog\Models\LearningEntry;
use Modules\LearningCatalog\Repositories\Contracts\LearningEntryRepositoryInterface;

class LearningEntryService
{
    public function __construct(private readonly LearningEntryRepositoryInterface $entries) {}

    public function publish(PublishLearningEntryData $data): LearningEntry
    {
        $entry = DB::transaction(fn (): LearningEntry => $this->entries->createPublished($data));

        event(new LearningEntryPublished(
            eventId: (string) Str::uuid(),
            entryId: $entry->id,
            title: $entry->title,
            publishedAt: $entry->published_at->toDateTimeImmutable(),
        ));

        return $entry;
    }
}
