<?php

namespace Modules\Activity\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Activity\Policies\ActivityPolicy;
use Modules\Activity\Repositories\Contracts\ActivityRepositoryInterface;
use Modules\Activity\Repositories\Eloquent\EloquentActivityRepository;
use Spatie\Activitylog\Models\Activity;

class ActivityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ActivityRepositoryInterface::class, EloquentActivityRepository::class);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(module_path('Activity', 'routes/web.php'));
        Route::prefix('api/v1')->middleware(['api', 'auth:sanctum', 'active-user', 'throttle:taskflow-api'])->as('api.v1.')->group(module_path('Activity', 'routes/api.php'));
        $this->loadViewsFrom(module_path('Activity', 'resources/views'), 'activity');
        Gate::policy(Activity::class, ActivityPolicy::class);
    }
}
