<?php

namespace Modules\Tasks\Repositories\Contracts;

use App\Models\User;
use Illuminate\Support\Collection;
use Modules\Projects\Models\Project;
use Modules\Tasks\Models\Task;

interface TaskWatcherRepositoryInterface
{
    /** @return Collection<int, User> */
    public function forTask(Task $task): Collection;

    public function ensureWatching(Task $task, User $user): void;

    public function removeWatching(Task $task, User $user): void;

    public function removeForProject(Project $project, User $user): int;

    public function removeForUser(User $user): int;

    public function isWatching(Task $task, User $user): bool;

    /** @return Collection<int, User> */
    public function eligibleWatchers(Task $task): Collection;
}
