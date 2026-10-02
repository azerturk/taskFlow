<?php

namespace Modules\Activity\Policies;

use App\Enums\PermissionName;
use App\Models\User;

class ActivityPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isActive()
            && $user->hasPermissionTo(PermissionName::ActivityView->value);
    }
}
