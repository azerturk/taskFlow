<?php

use Illuminate\Support\Facades\Route;
use Modules\Dashboard\Http\Controllers\Api\V1\DashboardController;

Route::middleware('abilities:dashboard:read')->prefix('dashboard')->group(function (): void {
    Route::get('/summary', [DashboardController::class, 'summary'])->name('dashboard.summary');
    Route::get('/my-tasks', [DashboardController::class, 'myTasks'])->name('dashboard.my-tasks');
    Route::get('/reported', [DashboardController::class, 'reported'])->name('dashboard.reported');
    Route::get('/watched', [DashboardController::class, 'watched'])->name('dashboard.watched');
    Route::get('/overdue', [DashboardController::class, 'overdue'])->name('dashboard.overdue');
});
