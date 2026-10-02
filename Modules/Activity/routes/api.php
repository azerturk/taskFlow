<?php

use Illuminate\Support\Facades\Route;
use Modules\Activity\Http\Controllers\Api\V1\ActivityController;

Route::middleware('abilities:activity:read')->group(function (): void {
    Route::get('/activity', [ActivityController::class, 'index'])->name('activity.index');
    Route::get('/projects/{project}/activity', [ActivityController::class, 'forProject'])->name('projects.activity');
    Route::get('/tasks/{task}/activity', [ActivityController::class, 'forTask'])->name('tasks.activity');
});
