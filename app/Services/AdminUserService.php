<?php

namespace App\Services;

use App\Data\ChangeOwnPasswordData;
use App\Data\CreateAdminUserData;
use App\Data\ResetAdminUserPasswordData;
use App\Data\UpdateAdminUserData;
use App\Enums\AccountStatus;
use App\Enums\UserRole;
use App\Exceptions\AdminLifecycleConflict;
use App\Models\User;
use App\Repositories\Contracts\PersonalAccessTokenRepositoryInterface;
use App\Repositories\Contracts\SessionRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Modules\Activity\Enums\ActivityEvent;
use Modules\Tasks\Repositories\Contracts\TaskRepositoryInterface;
use Modules\Tasks\Repositories\Contracts\TaskWatcherRepositoryInterface;
use Modules\Tasks\Services\TaskWatcherNotificationService;

class AdminUserService
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly PersonalAccessTokenRepositoryInterface $tokens,
        private readonly SessionRepositoryInterface $sessions,
        private readonly TaskRepositoryInterface $tasks,
        private readonly TaskWatcherRepositoryInterface $watchers,
        private readonly TaskWatcherNotificationService $notifications,
        private readonly SecurityAuditService $audit,
    ) {}

    public function paginate(?string $search, ?string $role, int $perPage = 12): LengthAwarePaginator
    {
        return $this->users->paginateForAdministration($search, $role, $perPage);
    }

    public function editPage(User $user): array
    {
        return ['managedUser' => $this->users->prepareForAdministration($user), 'roles' => UserRole::cases()];
    }

    public function create(CreateAdminUserData $data, ?User $actor = null): User
    {
        return DB::transaction(function () use ($data, $actor): User {
            $user = $this->users->create([
                'name' => $data->name,
                'email' => $this->normalizeEmail($data->email),
                'password' => Hash::make($data->password),
                'status' => AccountStatus::Active->value,
            ], $data->role);
            $this->audit->record($actor ?: $user, $user, ActivityEvent::UserCreated, [
                'user_id' => $user->id,
                'role' => $data->role->value,
                'status' => AccountStatus::Active->value,
            ]);

            return $user;
        });
    }

    public function update(User $user, UpdateAdminUserData $data, ?User $actor = null): User
    {
        return DB::transaction(function () use ($user, $data, $actor): User {
            $this->ensureLastActiveAdminIsRetained($user, $data->role);
            $oldRole = $this->users->roleName($user);
            $user = $this->users->updateIdentityAndRole($user, [
                'name' => $data->name,
                'email' => $this->normalizeEmail($data->email),
            ], $data->role);
            $this->audit->record($actor ?: $user, $user, ActivityEvent::UserUpdated, [
                'user_id' => $user->id,
                'old_role' => $oldRole,
                'new_role' => $data->role->value,
            ]);

            return $user;
        });
    }

    public function suspend(User $user, User $actor): User
    {
        return DB::transaction(function () use ($user, $actor): User {
            if (! $user->isActive()) {
                return $this->users->prepareForAdministration($user);
            }

            $this->ensureLastActiveAdminCanBeSuspended($user);
            $assignedTasks = $this->tasks->lockOpenAssignmentsFor($user);
            foreach ($assignedTasks as $task) {
                $oldVersion = $task->version;
                $task = $this->tasks->unassignForSuspension($task);
                $this->audit->record($actor, $task, ActivityEvent::TaskAssigned, [
                    'project_id' => $task->project_id,
                    'task_id' => $task->id,
                    'old_assignee_id' => $user->id,
                    'old_assignee_name' => $user->name ?: $user->email,
                    'new_assignee_id' => null,
                    'new_assignee_name' => null,
                    'reason' => 'assignee_suspended',
                    'old' => [
                        'assignee_id' => $user->id,
                        'assignee_name' => $user->name ?: $user->email,
                        'version' => $oldVersion,
                    ],
                    'new' => ['assignee_id' => null, 'assignee_name' => null, 'version' => $task->version],
                ]);
            }

            $watcherCount = $this->watchers->removeForUser($user);
            $tokenCount = $this->tokens->revokeAllFor($user);
            $sessionCount = $this->sessions->deleteForUser($user);
            $user = $this->users->setStatus($user, AccountStatus::Suspended);

            foreach ($assignedTasks as $task) {
                $this->notifications->notify($task, $actor, ActivityEvent::TaskAssigned);
            }

            $this->audit->record($actor, $user, ActivityEvent::UserSuspended, [
                'user_id' => $user->id,
                'unassigned_task_count' => $assignedTasks->count(),
                'watcher_subscription_count' => $watcherCount,
                'revoked_token_count' => $tokenCount,
                'revoked_session_count' => $sessionCount,
            ]);

            return $user;
        });
    }

    public function reactivate(User $user, User $actor): User
    {
        return DB::transaction(function () use ($user, $actor): User {
            $user = $this->users->setStatus($user, AccountStatus::Active);
            $this->audit->record($actor, $user, ActivityEvent::UserReactivated, ['user_id' => $user->id]);

            return $user;
        });
    }

    public function resetPassword(User $user, ResetAdminUserPasswordData $data, User $actor): void
    {
        DB::transaction(function () use ($user, $data, $actor): void {
            $this->users->updatePassword($user, Hash::make($data->password), Str::random(60));
            $this->tokens->revokeAllFor($user);
            $this->sessions->deleteForUser($user);
            $this->audit->record($actor, $user, ActivityEvent::UserPasswordReset, ['user_id' => $user->id]);
        });
    }

    public function changeOwnPassword(User $user, ChangeOwnPasswordData $data, string $currentSessionId): void
    {
        DB::transaction(function () use ($user, $data, $currentSessionId): void {
            $this->users->updatePassword($user, Hash::make($data->password), Str::random(60));
            $this->tokens->revokeAllFor($user);
            $this->sessions->deleteForUser($user, $currentSessionId);
            $this->audit->record($user, $user, ActivityEvent::UserPasswordChanged, ['user_id' => $user->id]);
        });
    }

    private function ensureLastActiveAdminIsRetained(User $user, UserRole $nextRole): void
    {
        if ($nextRole === UserRole::Admin || $this->users->roleName($user) !== UserRole::Admin->value) {
            return;
        }
        if ($this->users->activeAdministratorCountForUpdate() <= 1) {
            throw new AdminLifecycleConflict('The last Administrator cannot be assigned another global role.');
        }
    }

    private function ensureLastActiveAdminCanBeSuspended(User $user): void
    {
        if ($this->users->roleName($user) === UserRole::Admin->value && $this->users->activeAdministratorCountForUpdate() <= 1) {
            throw new AdminLifecycleConflict('The last active Administrator cannot be suspended.');
        }
    }

    private function normalizeEmail(string $email): string
    {
        return Str::lower(trim($email));
    }
}
