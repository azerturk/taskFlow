<?php

namespace Modules\Activity\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Modules\Activity\Enums\ActivityEvent;
use Modules\Activity\Support\ActivitySanitizer;

class ActivityRecorder
{
    public function __construct(private readonly ActivitySanitizer $sanitizer) {}

    public function record(ActivityEvent $event, User $actor, Model $subject, array $properties = []): void
    {
        $properties = $this->sanitize($properties);
        $properties['schema_version'] = 1;
        activity($event->value)->causedBy($actor)->performedOn($subject)->withProperties($properties)->event($event->value)->log($event->value);
    }

    /** @param array<string, mixed> $properties @return array<string, mixed> */
    public function sanitize(array $properties): array
    {
        return $this->sanitizer->sanitize($properties);
    }
}
