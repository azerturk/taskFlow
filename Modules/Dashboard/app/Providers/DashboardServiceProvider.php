<?php

namespace Modules\Dashboard\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Modules\Dashboard\Livewire\QuickTaskCreate;

class DashboardServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Livewire::component('dashboard.quick-task-create', QuickTaskCreate::class);
        $this->loadRoutesFrom(module_path('Dashboard', 'routes/web.php'));
        Route::prefix('api/v1')->middleware(['api', 'auth:sanctum', 'active-user', 'throttle:taskflow-api'])->as('api.v1.')->group(module_path('Dashboard', 'routes/api.php'));
        $this->loadViewsFrom(module_path('Dashboard', 'resources/views'), 'dashboard');
    }
}
