<?php

use App\Http\Middleware\EnsureActiveUser;
use Illuminate\Auth\Middleware\AuthenticateSession;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\ApplicationBuilder;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Routing\Middleware\ThrottleRequestsWithRedis;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;
use Modules\Projects\Exceptions\DuplicateProjectMember;
use Modules\Projects\Exceptions\InvalidProjectMember;
use Modules\Projects\Exceptions\InvalidProjectTransition;
use Modules\Projects\Exceptions\MemberHasOpenAssignments;
use Modules\Projects\Exceptions\ProjectKeyImmutable;
use Modules\Projects\Exceptions\ProjectReadOnly;
use Modules\Tasks\Exceptions\InvalidAssignee;
use Modules\Tasks\Exceptions\InvalidTaskComment;
use Modules\Tasks\Exceptions\InvalidTaskLabel;
use Modules\Tasks\Exceptions\InvalidTaskRankPosition;
use Modules\Tasks\Exceptions\InvalidTaskStatusTransition;
use Modules\Tasks\Exceptions\InvalidWatcher;
use Modules\Tasks\Exceptions\LabelOutsideProject;
use Modules\Tasks\Exceptions\ParentTaskInvalid;
use Modules\Tasks\Exceptions\TaskMutationNotAllowed;
use Modules\Tasks\Exceptions\TaskVersionConflict;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

$application = new Application(dirname(__DIR__));

foreach (['APP_PACKAGES_CACHE', 'APP_SERVICES_CACHE', 'APP_CONFIG_CACHE', 'APP_ROUTES_CACHE', 'APP_EVENTS_CACHE'] as $cacheVariable) {
    $cachePath = getenv($cacheVariable);

    if (is_string($cachePath) && preg_match('/^[A-Za-z]:[\\\\\/]/', $cachePath) === 1) {
        $application->addAbsoluteCachePathPrefix(substr($cachePath, 0, 2));
    }
}

return (new ApplicationBuilder($application))
    ->withKernels()
    ->withEvents()
    ->withCommands()
    ->withProviders()
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'abilities' => CheckAbilities::class,
            'ability' => CheckForAnyAbility::class,
            'active-user' => EnsureActiveUser::class,
        ]);
        $middleware->priority([
            HandlePrecognitiveRequests::class,
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
            ShareErrorsFromSession::class,
            AuthenticatesRequests::class,
            EnsureActiveUser::class,
            CheckAbilities::class,
            CheckForAnyAbility::class,
            ThrottleRequests::class,
            ThrottleRequestsWithRedis::class,
            AuthenticateSession::class,
            SubstituteBindings::class,
            Authorize::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
        $exceptions->render(function (NotFoundHttpException $exception, Request $request) {
            if (! ($request->is('api/*') || $request->expectsJson())) {
                return null;
            }

            return response()->json([
                'message' => 'The requested resource was not found.',
                'code' => 'resource_not_found',
                'errors' => [],
            ], 404);
        });
        $exceptions->render(function (MemberHasOpenAssignments $exception, Request $request) {
            if (! ($request->is('api/*') || $request->expectsJson())) {
                return null;
            }

            return response()->json([
                'message' => 'The member has open assignments.',
                'code' => 'member_has_open_assignments',
                'errors' => ['user_id' => ['Reassign or unassign open work before removal.']],
                'meta' => ['open_assignment_count' => $exception->count],
            ], 409);
        });
        $exceptions->render(function (TaskVersionConflict $exception, Request $request) {
            if (! ($request->is('api/*') || $request->expectsJson())) {
                return back()->with('error', 'This task changed while you were working. Refresh the page and try again.');
            }

            return response()->json([
                'message' => 'The task was changed by another request.',
                'code' => 'task_version_conflict',
                'errors' => ['expected_version' => ['Refresh the task and try again.']],
            ], 409);
        });
        $exceptions->render(function (InvalidTaskRankPosition $exception, Request $request) {
            if (! ($request->is('api/*') || $request->expectsJson())) {
                return back()->with('error', 'That task position is no longer available. Refresh the page and try again.');
            }

            return response()->json([
                'message' => 'The requested task position is no longer available.',
                'code' => 'invalid_task_rank_position',
                'errors' => ['position' => ['Refresh the task list and try again.']],
            ], 409);
        });
        $exceptions->render(function (InvalidTaskStatusTransition $exception, Request $request) {
            if (! ($request->is('api/*') || $request->expectsJson())) {
                return back()->with('error', 'That task status transition is not allowed.');
            }

            return response()->json([
                'message' => 'The requested task status transition is not allowed.',
                'code' => 'invalid_task_status_transition',
                'errors' => ['status' => ['Choose one of the allowed next statuses.']],
            ], 409);
        });
        $exceptions->render(function (InvalidProjectTransition $exception, Request $request) {
            if (! ($request->is('api/*') || $request->expectsJson())) {
                return back()->with('error', 'That project lifecycle transition is not allowed.');
            }

            return response()->json([
                'message' => 'The requested project lifecycle transition is not allowed.',
                'code' => 'invalid_project_transition',
                'errors' => ['status' => ['Choose an allowed next project status.']],
            ], 409);
        });
        $exceptions->render(function (ProjectReadOnly $exception, Request $request) {
            if (! ($request->is('api/*') || $request->expectsJson())) {
                return back()->with('error', 'This project is read-only.');
            }

            return response()->json([
                'message' => 'This project is read-only.',
                'code' => 'project_read_only',
                'errors' => [],
            ], 409);
        });
        $exceptions->render(function (ProjectKeyImmutable $exception, Request $request) {
            if (! ($request->is('api/*') || $request->expectsJson())) {
                return back()->withErrors(['key' => 'The project key cannot change after issue allocation.']);
            }

            return response()->json([
                'message' => 'The project key cannot change after issue allocation.',
                'code' => 'project_key_immutable',
                'errors' => ['key' => ['Keep the current project key.']],
            ], 409);
        });
        $exceptions->render(function (DuplicateProjectMember $exception, Request $request) {
            if (! ($request->is('api/*') || $request->expectsJson())) {
                return back()->withErrors(['user_id' => 'This user is already a project member.']);
            }

            return response()->json([
                'message' => 'This user is already a project member.',
                'code' => 'duplicate_project_member',
                'errors' => ['user_id' => ['Choose a user who is not already a member.']],
            ], 409);
        });
        $exceptions->render(function (InvalidProjectMember $exception, Request $request) {
            if (! ($request->is('api/*') || $request->expectsJson())) {
                return back()->withErrors(['user_id' => 'Select an eligible project member.']);
            }

            return response()->json([
                'message' => 'The requested project member is invalid.',
                'code' => 'invalid_project_member',
                'errors' => ['user_id' => ['Select an eligible project member.']],
            ], 422);
        });
        $exceptions->render(function (InvalidAssignee $exception, Request $request) {
            if (! ($request->is('api/*') || $request->expectsJson())) {
                return back()->withErrors(['assignee_id' => 'Select an active member of this project.']);
            }

            return response()->json([
                'message' => 'The requested assignee is invalid.',
                'code' => 'invalid_assignee',
                'errors' => ['assignee_id' => ['Select an active member of this project.']],
            ], 422);
        });
        $exceptions->render(function (ParentTaskInvalid $exception, Request $request) {
            if (! ($request->is('api/*') || $request->expectsJson())) {
                return back()->withErrors(['parent_id' => 'Select a valid parent task from this project.']);
            }

            return response()->json([
                'message' => 'The requested parent task is invalid.',
                'code' => 'parent_task_invalid',
                'errors' => ['parent_id' => ['Select a valid parent task from this project.']],
            ], 422);
        });
        $exceptions->render(function (LabelOutsideProject $exception, Request $request) {
            if (! ($request->is('api/*') || $request->expectsJson())) {
                return back()->withErrors(['label_ids' => 'Every selected label must belong to this project.']);
            }

            return response()->json([
                'message' => 'One or more labels are invalid for this project.',
                'code' => 'label_outside_project',
                'errors' => ['label_ids' => ['Every selected label must belong to this project.']],
            ], 422);
        });
        $exceptions->render(function (InvalidWatcher $exception, Request $request) {
            if (! ($request->is('api/*') || $request->expectsJson())) {
                return back()->withErrors(['user_id' => 'Select an active member of this project.']);
            }

            return response()->json([
                'message' => 'The requested watcher is invalid.',
                'code' => 'invalid_watcher',
                'errors' => ['user_id' => ['Select an active member of this project.']],
            ], 422);
        });
        $exceptions->render(function (InvalidTaskLabel $exception, Request $request) {
            if (! ($request->is('api/*') || $request->expectsJson())) {
                return back()->withErrors(['name' => 'Choose a unique label name containing letters or numbers.']);
            }

            return response()->json([
                'message' => 'The requested label is invalid.',
                'code' => 'invalid_task_label',
                'errors' => ['name' => ['Choose a unique label name containing letters or numbers.']],
            ], 422);
        });
        $exceptions->render(function (InvalidTaskComment $exception, Request $request) {
            if (! ($request->is('api/*') || $request->expectsJson())) {
                return back()->withErrors(['body' => 'Enter a comment of at most 5,000 non-whitespace characters.']);
            }

            return response()->json([
                'message' => 'The requested comment is invalid.',
                'code' => 'invalid_task_comment',
                'errors' => ['body' => ['Enter a comment of at most 5,000 non-whitespace characters.']],
            ], 422);
        });
        $exceptions->render(function (TaskMutationNotAllowed $exception, Request $request) {
            if (! ($request->is('api/*') || $request->expectsJson())) {
                return back()->with('error', 'This task operation is not allowed in its current state.');
            }

            return response()->json([
                'message' => 'This task operation is not allowed in its current state.',
                'code' => 'task_operation_not_allowed',
                'errors' => [],
            ], 409);
        });
    })->create();
