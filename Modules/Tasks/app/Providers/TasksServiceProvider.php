<?php

namespace Modules\Tasks\Providers;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Modules\Projects\Models\Project;
use Modules\Tasks\Livewire\TaskCommentForm;
use Modules\Tasks\Livewire\TaskFilters;
use Modules\Tasks\Livewire\TaskStatusSelector;
use Modules\Tasks\Models\Task;
use Modules\Tasks\Models\TaskAttachment;
use Modules\Tasks\Models\TaskComment;
use Modules\Tasks\Models\TaskLabel;
use Modules\Tasks\Policies\TaskPolicy;
use Modules\Tasks\Repositories\Contracts\TaskAttachmentRepositoryInterface;
use Modules\Tasks\Repositories\Contracts\TaskCommentRepositoryInterface;
use Modules\Tasks\Repositories\Contracts\TaskLabelRepositoryInterface;
use Modules\Tasks\Repositories\Contracts\TaskRepositoryInterface;
use Modules\Tasks\Repositories\Contracts\TaskWatcherRepositoryInterface;
use Modules\Tasks\Repositories\Eloquent\EloquentTaskAttachmentRepository;
use Modules\Tasks\Repositories\Eloquent\EloquentTaskCommentRepository;
use Modules\Tasks\Repositories\Eloquent\EloquentTaskLabelRepository;
use Modules\Tasks\Repositories\Eloquent\EloquentTaskRepository;
use Modules\Tasks\Repositories\Eloquent\EloquentTaskWatcherRepository;

class TasksServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(TaskRepositoryInterface::class, EloquentTaskRepository::class);
        $this->app->bind(TaskCommentRepositoryInterface::class, EloquentTaskCommentRepository::class);
        $this->app->bind(TaskAttachmentRepositoryInterface::class, EloquentTaskAttachmentRepository::class);
        $this->app->bind(TaskWatcherRepositoryInterface::class, EloquentTaskWatcherRepository::class);
        $this->app->bind(TaskLabelRepositoryInterface::class, EloquentTaskLabelRepository::class);
    }

    public function boot(): void
    {
        Route::bind('task', fn (string $value): Task => $this->app->make(TaskRepositoryInterface::class)
            ->findVisibleOrFail(request()->user(), (int) $value));
        Route::bind('comment', function (string $value, RoutingRoute $route): TaskComment {
            $task = $route->parameter('task');
            if (! $task instanceof Task) {
                throw (new ModelNotFoundException)->setModel(TaskComment::class, [$value]);
            }

            return $this->app->make(TaskCommentRepositoryInterface::class)->findForTaskOrFail($task, (int) $value);
        });
        $attachmentBinding = function (string $value, RoutingRoute $route): TaskAttachment {
            $task = $route->parameter('task');
            if (! $task instanceof Task) {
                throw (new ModelNotFoundException)->setModel(TaskAttachment::class, [$value]);
            }

            return $this->app->make(TaskAttachmentRepositoryInterface::class)->findForTaskOrFail($task, (int) $value);
        };
        Route::bind('attachment', $attachmentBinding);
        Route::bind('media', $attachmentBinding);
        Route::bind('label', function (string $value, RoutingRoute $route): TaskLabel {
            $project = $route->parameter('project');
            if (! $project instanceof Project) {
                throw (new ModelNotFoundException)->setModel(TaskLabel::class, [$value]);
            }

            return $this->app->make(TaskLabelRepositoryInterface::class)->findForProjectOrFail($project, (int) $value);
        });

        Livewire::component('tasks.task-filters', TaskFilters::class);
        Livewire::component('tasks.task-status-selector', TaskStatusSelector::class);
        Livewire::component('tasks.task-comment-form', TaskCommentForm::class);
        $this->loadRoutesFrom(module_path('Tasks', 'routes/web.php'));
        Route::prefix('api/v1')->middleware(['api', 'auth:sanctum', 'active-user', 'throttle:taskflow-api'])->as('api.v1.')->group(module_path('Tasks', 'routes/api.php'));
        $this->loadViewsFrom(module_path('Tasks', 'resources/views'), 'tasks');
        $this->loadMigrationsFrom(module_path('Tasks', 'database/migrations'));
        Gate::policy(Task::class, TaskPolicy::class);
    }
}
