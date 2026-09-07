<?php

use Illuminate\Support\Facades\Route;
use Modules\Tasks\Http\Controllers\Api\V1\BacklogController;
use Modules\Tasks\Http\Controllers\Api\V1\TaskAttachmentController;
use Modules\Tasks\Http\Controllers\Api\V1\TaskBoardController;
use Modules\Tasks\Http\Controllers\Api\V1\TaskCommentController;
use Modules\Tasks\Http\Controllers\Api\V1\TaskController;
use Modules\Tasks\Http\Controllers\Api\V1\TaskLabelController;
use Modules\Tasks\Http\Controllers\Api\V1\TaskWatcherController;

Route::middleware('abilities:tasks:read')->group(function (): void {
    Route::get('/tasks', [TaskController::class, 'index'])->middleware('throttle:taskflow-search')->name('tasks.index');
    Route::get('/tasks/{task}', [TaskController::class, 'show'])->name('tasks.show');
    Route::get('/projects/{project}/labels', [TaskLabelController::class, 'index'])->name('projects.labels.index');
    Route::get('/projects/{project}/backlog', [BacklogController::class, 'show'])->middleware('throttle:taskflow-search')->name('projects.backlog');
    Route::get('/projects/{project}/board', [TaskBoardController::class, 'show'])->middleware('throttle:taskflow-search')->name('projects.board');
    Route::get('/tasks/{task}/watchers', [TaskWatcherController::class, 'index'])->name('tasks.watchers.index');
    Route::get('/tasks/{task}/comments', [TaskCommentController::class, 'index'])->name('tasks.comments.index');
    Route::get('/tasks/{task}/media', [TaskAttachmentController::class, 'index'])->name('tasks.media.index');
    Route::get('/tasks/{task}/media/{media}/preview', [TaskAttachmentController::class, 'preview'])->name('tasks.media.preview');
    Route::get('/tasks/{task}/media/{media}/download', [TaskAttachmentController::class, 'download'])->name('tasks.media.download');
});
Route::middleware('abilities:tasks:write')->group(function (): void {
    Route::post('/tasks', [TaskController::class, 'store'])->name('tasks.store');
    Route::put('/tasks/{task}', [TaskController::class, 'update'])->name('tasks.update');
    Route::put('/tasks/{task}/labels', [TaskController::class, 'syncLabels'])->name('tasks.labels.sync');
    Route::delete('/tasks/{task}', [TaskController::class, 'destroy'])->name('tasks.destroy');
    Route::patch('/tasks/{task}/assignee', [TaskController::class, 'assign'])->name('tasks.assign');
    Route::patch('/tasks/{task}/status', [TaskController::class, 'changeStatus'])->name('tasks.status');
    Route::patch('/tasks/{task}/rank', [TaskController::class, 'reorder'])->name('tasks.reorder');
    Route::post('/tasks/{task}/media', [TaskAttachmentController::class, 'store'])->middleware('throttle:taskflow-media-upload')->name('tasks.media.store');
    Route::delete('/tasks/{task}/media/{media}', [TaskAttachmentController::class, 'destroy'])->name('tasks.media.destroy');
    Route::post('/projects/{project}/labels', [TaskLabelController::class, 'store'])->name('projects.labels.store');
    Route::patch('/projects/{project}/labels/{label}', [TaskLabelController::class, 'update'])->name('projects.labels.update');
    Route::delete('/projects/{project}/labels/{label}', [TaskLabelController::class, 'destroy'])->name('projects.labels.destroy');
    Route::post('/tasks/{task}/watchers', [TaskWatcherController::class, 'store'])->name('tasks.watchers.store');
    Route::delete('/tasks/{task}/watchers/{user}', [TaskWatcherController::class, 'destroy'])->name('tasks.watchers.destroy');
});
Route::middleware('abilities:comments:write')->group(function (): void {
    Route::post('/tasks/{task}/comments', [TaskCommentController::class, 'store'])->name('tasks.comments.store');
    Route::delete('/tasks/{task}/comments/{comment}', [TaskCommentController::class, 'destroy'])->name('tasks.comments.destroy');
});
