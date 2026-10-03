<?php

namespace Modules\LearningCatalog\Contracts;

use Modules\LearningCatalog\Data\PublishedLearningEntryData;

interface PublishedLearningEntryFeed
{
    /** @return list<PublishedLearningEntryData> */
    public function all(): array;
}
