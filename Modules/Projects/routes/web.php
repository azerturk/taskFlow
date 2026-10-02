<?php

use Illuminate\Support\Facades\Route;
use Modules\Projects\Http\Controllers\ProjectController;
use Modules\Projects\Http\Controllers\ProjectMemberController;

Route::middleware(['web', 'auth', 'active-user'])->prefix('projects')->as('projects.')->group(function (): void {
    Route::get('/', [ProjectController::class, 'index'])->middleware('throttle:taskflow-search')->name('index');
    Route::get('/create', [ProjectController::class, 'create'])->name('create');
    Route::post('/', [ProjectController::class, 'store'])->name('store');
    Route::get('/{project}', [ProjectController::class, 'show'])->name('show');
    Route::get('/{project}/edit', [ProjectController::class, 'edit'])->name('edit');
    Route::put('/{project}', [ProjectController::class, 'update'])->name('update');
    Route::patch('/{project}/archive', [ProjectController::class, 'archive'])->name('archive');
    Route::patch('/{project}/activate', [ProjectController::class, 'activate'])->name('activate');
    Route::patch('/{project}/complete', [ProjectController::class, 'complete'])->name('complete');
    Route::get('/{project}/members', [ProjectMemberController::class, 'index'])->name('members.index');
    Route::post('/{project}/members', [ProjectMemberController::class, 'store'])->name('members.store');
    Route::patch('/{project}/members/{user}', [ProjectMemberController::class, 'update'])->name('members.update');
    Route::delete('/{project}/members/{user}', [ProjectMemberController::class, 'destroy'])->name('members.destroy');
});
