<?php

namespace App\Repositories\Eloquent;

use App\Models\User;
use App\Repositories\Contracts\SessionRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DatabaseSessionRepository implements SessionRepositoryInterface
{
    public function deleteForUser(User $user, ?string $exceptSessionId = null): int
    {
        if (! Schema::hasTable('sessions')) {
            return 0;
        }

        return DB::table('sessions')
            ->where('user_id', $user->id)
            ->when($exceptSessionId !== null, fn ($query) => $query->where('id', '!=', $exceptSessionId))
            ->delete();
    }
}
