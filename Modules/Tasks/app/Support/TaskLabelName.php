<?php

namespace Modules\Tasks\Support;

use Illuminate\Support\Str;
use Modules\Tasks\Exceptions\InvalidTaskLabel;

final readonly class TaskLabelName
{
    private function __construct(public string $name, public string $slug) {}

    public static function from(string $name): self
    {
        $name = trim($name);
        $slug = Str::slug($name);

        if ($slug === '') {
            throw new InvalidTaskLabel('A label name must contain letters or numbers.');
        }

        return new self($name, $slug);
    }
}
