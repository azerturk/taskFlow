<?php

namespace Modules\Projects\Data;

final readonly class AllocatedIssueNumberData
{
    public function __construct(
        public int $issueNumber,
        public string $displayKey,
    ) {}

    public static function forProject(string $key, int $issueNumber): self
    {
        return new self($issueNumber, $key.'-'.$issueNumber);
    }
}
