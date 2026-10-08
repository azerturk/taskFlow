<?php

namespace Modules\LearningCatalog\Repositories\Contracts;

use Modules\LearningCatalog\Data\PublishLearningEntryData;
use Modules\LearningCatalog\Models\LearningEntry;

interface LearningEntryRepositoryInterface
{
    public function createPublished(PublishLearningEntryData $data): LearningEntry;

    /** @return list<LearningEntry> */
    public function allPublished(): array;
}
