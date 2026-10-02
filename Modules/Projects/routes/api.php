<?php

use Illuminate\Support\Facades\Route;
use Modules\Projects\Http\Controllers\Api\V1\ProjectController;
use Modules\Projects\Http\Controllers\Api\V1\ProjectMemberController;

Route::middleware('abilities:projects:read')->group(function (): void {
    Route::get('/projects', [ProjectController::class, 'index'])->middleware('throttle:taskflow-search')->name('projects.index');
    Route::get('/projects/{project}', [ProjectController::class, 'show'])->name('projects.show');
    Route::get('/projects/{project}/members', [ProjectMemberController::class, 'index'])->name('projects.members.index');
});
Route::middleware('abilities:projects:write')->group(function (): void {
    Route::post('/projects', [ProjectController::class, 'store'])->name('projects.store');
    Route::put('/projects/{project}', [ProjectController::class, 'update'])->name('projects.update');
    Route::patch('/projects/{project}/status', [ProjectController::class, 'changeStatus'])->name('projects.status');
    Route::post('/projects/{project}/members', [ProjectMemberController::class, 'store'])->name('projects.members.store');
    Route::patch('/projects/{project}/members/{user}', [ProjectMemberController::class, 'update'])->name('projects.members.update');
    Route::delete('/projects/{project}/members/{user}', [ProjectMemberController::class, 'destroy'])->name('projects.members.destroy');
});
