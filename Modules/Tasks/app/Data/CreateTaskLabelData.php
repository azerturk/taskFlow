<?php

namespace Modules\Tasks\Data;

use Modules\Tasks\Enums\TaskLabelColor;

final readonly class CreateTaskLabelData
{
    public function __construct(public string $name, public TaskLabelColor $color) {}

    public static function fromArray(array $data): self
    {
        return new self(trim($data['name']), TaskLabelColor::from($data['color']));
    }
}
