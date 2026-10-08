<?php

namespace Modules\LearningCatalog\Data;

use Illuminate\Support\Str;
use Modules\LearningCatalog\Exceptions\InvalidLearningEntryTitle;

final readonly class PublishLearningEntryData
{
    public string $title;

    public function __construct(string $title)
    {
        $title = Str::trim($title);

        if ($title === '' || mb_strlen($title, 'UTF-8') > 255) {
            throw new InvalidLearningEntryTitle('Learning entry title must contain between 1 and 255 characters.');
        }

        $this->title = $title;
    }
}
