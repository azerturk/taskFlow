<?php

use Illuminate\Support\Facades\Route;
use Modules\Tasks\Http\Controllers\BacklogController;
use Modules\Tasks\Http\Controllers\TaskAttachmentController;
use Modules\Tasks\Http\Controllers\TaskBoardController;
use Modules\Tasks\Http\Controllers\TaskCommentController;
use Modules\Tasks\Http\Controllers\TaskController;
use Modules\Tasks\Http\Controllers\TaskLabelController;
use Modules\Tasks\Http\Controllers\TaskWatcherController;

Route::middleware(['web', 'auth', 'active-user'])->group(function (): void {
    Route::get('/tasks', [TaskController::class, 'index'])->middleware('throttle:taskflow-search')->name('tasks.index');
    Route::get('/projects/{project}/tasks/create', [TaskController::class, 'create'])->name('tasks.create');
    Route::get('/projects/{project}/backlog', [BacklogController::class, 'show'])->middleware('throttle:taskflow-search')->name('projects.backlog');
    Route::get('/projects/{project}/board', [TaskBoardController::class, 'show'])->middleware('throttle:taskflow-search')->name('projects.board');
    Route::post('/projects/{project}/tasks', [TaskController::class, 'store'])->name('tasks.store');
    Route::get('/tasks/{task}', [TaskController::class, 'show'])->name('tasks.show');
    Route::get('/tasks/{task}/edit', [TaskController::class, 'edit'])->name('tasks.edit');
    Route::put('/tasks/{task}', [TaskController::class, 'update'])->name('tasks.update');
    Route::put('/tasks/{task}/labels', [TaskController::class, 'syncLabels'])->name('tasks.labels.sync');
    Route::delete('/tasks/{task}', [TaskController::class, 'destroy'])->name('tasks.destroy');
    Route::patch('/tasks/{task}/assignee', [TaskController::class, 'assign'])->name('tasks.assign');
    Route::patch('/tasks/{task}/status', [TaskController::class, 'changeStatus'])->name('tasks.status');
    Route::patch('/tasks/{task}/rank', [TaskController::class, 'reorder'])->name('tasks.reorder');
    Route::post('/tasks/{task}/watchers', [TaskWatcherController::class, 'store'])->name('tasks.watchers.store');
    Route::delete('/tasks/{task}/watchers/{user}', [TaskWatcherController::class, 'destroy'])->name('tasks.watchers.destroy');
    Route::post('/tasks/{task}/comments', [TaskCommentController::class, 'store'])->name('tasks.comments.store');
    Route::delete('/tasks/{task}/comments/{comment}', [TaskCommentController::class, 'destroy'])->name('tasks.comments.destroy');
    Route::post('/tasks/{task}/media', [TaskAttachmentController::class, 'store'])->middleware('throttle:taskflow-media-upload')->name('tasks.media.store');
    Route::get('/tasks/{task}/media/{attachment}/preview', [TaskAttachmentController::class, 'preview'])->name('tasks.media.preview');
    Route::get('/tasks/{task}/media/{attachment}/download', [TaskAttachmentController::class, 'download'])->name('tasks.media.download');
    Route::delete('/tasks/{task}/media/{attachment}', [TaskAttachmentController::class, 'destroy'])->name('tasks.media.destroy');
    Route::post('/projects/{project}/labels', [TaskLabelController::class, 'store'])->name('projects.labels.store');
    Route::get('/projects/{project}/labels', [TaskLabelController::class, 'index'])->name('projects.labels.index');
    Route::patch('/projects/{project}/labels/{label}', [TaskLabelController::class, 'update'])->name('projects.labels.update');
    Route::delete('/projects/{project}/labels/{label}', [TaskLabelController::class, 'destroy'])->name('projects.labels.destroy');
});
