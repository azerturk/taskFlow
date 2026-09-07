<?php

use Illuminate\Support\Facades\Route;
use Modules\Activity\Http\Controllers\ActivityController;

Route::middleware(['web', 'auth', 'active-user'])->get('/activity', [ActivityController::class, 'index'])->name('activity.index');
Route::middleware(['web', 'auth', 'active-user'])->get('/projects/{project}/activity', [ActivityController::class, 'forProject'])->name('projects.activity');
Route::middleware(['web', 'auth', 'active-user'])->get('/tasks/{task}/activity', [ActivityController::class, 'forTask'])->name('tasks.activity');
