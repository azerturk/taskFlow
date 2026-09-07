<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Modules\Activity\Enums\ActivityEvent;
use Modules\Activity\Services\ActivityRecorder;

class SecurityAuditService
{
    /** @param array<string, mixed> $properties */
    public function __construct(private readonly ActivityRecorder $activity) {}

    public function record(User $actor, Model $subject, ActivityEvent $event, array $properties = []): void
    {
        $this->activity->record($event, $actor, $subject, $properties);
    }

    /** @param array<string, mixed> $properties
     * @return array<string, mixed>
     */
    public function sanitize(array $properties): array
    {
        return $this->activity->sanitize($properties);
    }
}
