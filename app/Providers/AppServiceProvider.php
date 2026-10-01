<?php

namespace App\Providers;

use App\Enums\PermissionName;
use App\Enums\UserRole;
use App\Http\Middleware\EnsureActiveUser;
use App\Models\User;
use App\Repositories\Contracts\NotificationRepositoryInterface;
use App\Repositories\Contracts\PersonalAccessTokenRepositoryInterface;
use App\Repositories\Contracts\SessionRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\Eloquent\DatabaseSessionRepository;
use App\Repositories\Eloquent\EloquentNotificationRepository;
use App\Repositories\Eloquent\EloquentPersonalAccessTokenRepository;
use App\Repositories\Eloquent\EloquentUserRepository;
use App\Services\WorkspaceHeaderQueryService;
use App\View\Composers\WorkspaceComposer;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(UserRepositoryInterface::class, EloquentUserRepository::class);
        $this->app->bind(PersonalAccessTokenRepositoryInterface::class, EloquentPersonalAccessTokenRepository::class);
        $this->app->bind(SessionRepositoryInterface::class, DatabaseSessionRepository::class);
        $this->app->bind(NotificationRepositoryInterface::class, EloquentNotificationRepository::class);
        $this->app->singleton(WorkspaceHeaderQueryService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Livewire::addPersistentMiddleware(EnsureActiveUser::class);

        RateLimiter::for('taskflow-api', function (Request $request): Limit {
            return Limit::perMinute(120)->by($this->actorKey($request));
        });

        RateLimiter::for('taskflow-token', function (Request $request): Limit {
            $email = Str::lower(trim((string) $request->input('email')));

            return Limit::perMinute(5)->by($email.'|'.$request->ip());
        });

        RateLimiter::for('taskflow-login', function (Request $request): Limit {
            return Limit::perMinute(5)->by($this->credentialKey($request));
        });

        RateLimiter::for('taskflow-media-upload', function (Request $request): Limit {
            return Limit::perMinute(10)->by($this->actorKey($request));
        });

        RateLimiter::for('taskflow-search', function (Request $request): Limit {
            return Limit::perMinute(30)->by($this->actorKey($request));
        });

        Gate::define('viewDashboard', fn (User $user): bool => $user->hasPermissionTo(PermissionName::DashboardView->value));
        Gate::define('manageUsers', fn (User $user): bool => $user->hasRole(UserRole::Admin->value)
            && $user->hasPermissionTo(PermissionName::UserRolesManage->value));
        View::composer(['layouts.app', 'components.workspace-header'], WorkspaceComposer::class);
    }

    private function credentialKey(Request $request): string
    {
        $email = Str::transliterate(Str::lower(trim((string) $request->input('email'))));

        return $email.'|'.$request->ip();
    }

    private function actorKey(Request $request): string
    {
        return ($request->user()?->getAuthIdentifier() ?: 'guest').'|'.$request->ip();
    }
}
