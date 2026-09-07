<?php

namespace Modules\Tasks\Data;

final readonly class SyncTaskLabelsData
{
    /** @param list<int> $labelIds */
    public function __construct(public array $labelIds) {}

    public static function fromArray(array $data): self
    {
        return new self(array_map('intval', $data['label_ids']));
    }
}
