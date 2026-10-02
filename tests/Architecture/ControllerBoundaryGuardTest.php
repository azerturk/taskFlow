<?php

use App\Http\Controllers\Api\V1\AuthenticationController;
use App\Repositories\Contracts\NotificationRepositoryInterface;
use App\Repositories\Contracts\PersonalAccessTokenRepositoryInterface;
use App\Repositories\Contracts\SessionRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use Modules\Activity\Http\Controllers\Api\V1\ActivityController;
use Modules\Activity\Repositories\Contracts\ActivityRepositoryInterface;
use Modules\Dashboard\Http\Controllers\Api\V1\DashboardController;
use Modules\Media\Repositories\Contracts\MediaRepositoryInterface;
use Modules\Projects\Http\Controllers\Api\V1\ProjectController;
use Modules\Projects\Http\Controllers\Api\V1\ProjectMemberController;
use Modules\Projects\Repositories\Contracts\ProjectMemberRepositoryInterface;
use Modules\Projects\Repositories\Contracts\ProjectRepositoryInterface;
use Modules\Tasks\Http\Controllers\Api\V1\BacklogController;
use Modules\Tasks\Http\Controllers\Api\V1\TaskAttachmentController;
use Modules\Tasks\Http\Controllers\Api\V1\TaskBoardController;
use Modules\Tasks\Http\Controllers\Api\V1\TaskCommentController;
use Modules\Tasks\Http\Controllers\Api\V1\TaskController;
use Modules\Tasks\Http\Controllers\Api\V1\TaskLabelController;
use Modules\Tasks\Http\Controllers\Api\V1\TaskWatcherController;
use Modules\Tasks\Repositories\Contracts\TaskAttachmentRepositoryInterface;
use Modules\Tasks\Repositories\Contracts\TaskCommentRepositoryInterface;
use Modules\Tasks\Repositories\Contracts\TaskLabelRepositoryInterface;
use Modules\Tasks\Repositories\Contracts\TaskRepositoryInterface;
use Modules\Tasks\Repositories\Contracts\TaskWatcherRepositoryInterface;
use Tests\Architecture\Support\SourceGuard;
use Tests\TestCase;

uses(TestCase::class);

function sourceFiles(string $directory): array
{
    $files = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path($directory)));

    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $path = str_replace('\\', '/', $file->getPathname());
            $files[$path] = file_get_contents($path);
        }
    }

    return $files;
}

function relativeSourcePath(string $path): string
{
    return SourceGuard::relativePath(base_path(), $path);
}

test('controllers do not execute Eloquent DB or Storage queries', function () {
    $violations = [];

    foreach (array_merge(sourceFiles('app/Http/Controllers'), sourceFiles('Modules')) as $path => $source) {
        if (! SourceGuard::isController($path)) {
            continue;
        }

        foreach (SourceGuard::controllerViolations($source) as $violation) {
            $violations[] = basename($path).': '.$violation;
        }
    }

    expect($violations)->toBe([]);
});

test('the application does not map every LogicException to a conflict response', function () {
    $source = file_get_contents(base_path('bootstrap/app.php'));

    expect($source)->not->toMatch('/render\(function \(LogicException .*\).*409/s');
});

test('only the four approved Livewire components exist and they do not query persistence directly', function () {
    $approved = [
        'Modules/Dashboard/app/Livewire/QuickTaskCreate.php',
        'Modules/Tasks/app/Livewire/TaskCommentForm.php',
        'Modules/Tasks/app/Livewire/TaskFilters.php',
        'Modules/Tasks/app/Livewire/TaskStatusSelector.php',
    ];
    $found = array_keys(array_filter(sourceFiles('Modules'), fn (string $source, string $path): bool => str_contains($path, '/app/Livewire/'), ARRAY_FILTER_USE_BOTH));
    sort($approved);
    sort($found);

    expect(array_map(fn (string $path): string => relativeSourcePath($path), $found))->toBe($approved);

    foreach ($found as $path) {
        $source = file_get_contents($path);

        expect($source, basename($path))->not->toMatch('/::query\(|\bDB::|\bStorage::|Repositories?\\\\/');
    }
});

test('request input and task media boundaries stay in their approved layers', function () {
    $requestAll = [];

    foreach (array_merge(sourceFiles('app'), sourceFiles('Modules')) as $path => $source) {
        if (str_contains($source, 'request()->all(')) {
            $requestAll[] = relativeSourcePath($path);
        }
    }

    expect($requestAll)->toBe([]);

    $taskSources = sourceFiles('Modules/Tasks/app');
    $storageViolations = [];

    foreach ($taskSources as $path => $source) {
        if (str_contains($path, '/Support/TaskAttachmentMediaBackfill.php')) {
            continue; // One-time legacy backfill; runtime media I/O belongs to Media.
        }

        if (preg_match('/\bStorage::|->store(?:As)?\(/', $source, $match)) {
            $storageViolations[] = relativeSourcePath($path).': '.$match[0];
        }
    }

    expect($storageViolations)->toBe([]);
});

test('module routes and API controllers retain module ownership and resource responses', function () {
    $routeViolations = [];

    foreach (sourceFiles('routes') as $path => $source) {
        if (str_contains($source, "Route::prefix('api/v1')") || str_contains($source, 'Route::prefix("api/v1")')) {
            $routeViolations[] = relativeSourcePath($path);
        }
    }

    expect($routeViolations)->toBe([]);

    foreach (sourceFiles('Modules') as $path => $source) {
        if (! str_contains($path, '/Http/Controllers/Api/V1/')) {
            continue;
        }

        expect($source, basename($path))->toMatch('/Http\\\\Resources\\\\|Resource::/');
    }
});

test('the executable Pest suite contains no skipped tests', function () {
    $skips = [];

    foreach (array_merge(sourceFiles('tests'), sourceFiles('Modules')) as $path => $source) {
        if (! str_contains($path, '/tests/')) {
            continue;
        }

        if (preg_match('/(?:->skip\(|\bskip\()/', $source, $match)) {
            $skips[] = relativeSourcePath($path).': '.$match[0];
        }
    }

    expect($skips)->toBe([]);
});

test('the Playwright suite contains exactly the ten approved journeys without focused or skipped cases', function () {
    $specification = file_get_contents(base_path('tests/e2e/taskflow.spec.js'));
    $configuration = file_get_contents(base_path('playwright.config.js'));

    preg_match_all("/test\('journey (\\d+): ([^']+)'/", $specification, $matches, PREG_SET_ORDER);

    expect(array_map(fn (array $match): int => (int) $match[1], $matches))
        ->toBe(range(1, 10))
        ->and($configuration)->toContain("name: 'desktop'")
        ->and($configuration)->toContain("name: 'mobile'")
        ->and($configuration)->toContain('forbidOnly: !!process.env.CI')
        ->and($specification)->not->toMatch('/\\btest\\.(?:only|skip|fixme)\\s*\\(/')
        ->and($configuration)->not->toMatch('/\\b(?:test|describe)\\.(?:only|skip)\\s*\\(/');
});

test('SQLite and MySQL profiles discover the same application and module suites', function () {
    $suiteDirectories = static function (string $configuration): array {
        preg_match_all('/<directory>([^<]+)<\\/directory>/', file_get_contents(base_path($configuration)), $matches);

        return $matches[1];
    };

    expect($suiteDirectories('phpunit-mysql.xml'))
        ->toBe($suiteDirectories('phpunit.xml'))
        ->and(file_get_contents(base_path('phpunit.xml')))->toContain('bootstrap="tests/bootstrap/sqlite.php"')
        ->and(file_get_contents(base_path('phpunit-mysql.xml')))->toContain('bootstrap="tests/bootstrap/mysql.php"');
});

test('enabled modules expose their documented repository bindings', function () {
    $bindings = [
        ProjectRepositoryInterface::class,
        ProjectMemberRepositoryInterface::class,
        TaskRepositoryInterface::class,
        TaskCommentRepositoryInterface::class,
        TaskAttachmentRepositoryInterface::class,
        TaskLabelRepositoryInterface::class,
        TaskWatcherRepositoryInterface::class,
        MediaRepositoryInterface::class,
        ActivityRepositoryInterface::class,
        UserRepositoryInterface::class,
        PersonalAccessTokenRepositoryInterface::class,
        SessionRepositoryInterface::class,
        NotificationRepositoryInterface::class,
    ];

    foreach ($bindings as $binding) {
        expect(app()->bound($binding), $binding)->toBeTrue();
    }
});

test('cross-module imports stay within the documented direct-dependency graph', function () {
    $allowed = [
        'Projects' => ['Activity', 'Tasks'],
        'Tasks' => ['Projects', 'Media', 'Activity'],
        'Activity' => ['Projects', 'Tasks'],
        'Dashboard' => ['Projects', 'Tasks', 'Activity'],
        'Media' => [],
    ];
    $violations = [];

    foreach ($allowed as $module => $dependencies) {
        foreach (sourceFiles("Modules/{$module}/app") as $path => $source) {
            preg_match_all('/use Modules\\\\([A-Za-z]+)\\\\/', $source, $matches);
            foreach (array_unique($matches[1]) as $dependency) {
                if ($dependency !== $module && ! in_array($dependency, $dependencies, true)) {
                    $violations[] = relativeSourcePath($path).": {$module} -> {$dependency}";
                }
            }
        }
    }

    expect($violations)->toBe([]);
});

test('cross-module dependencies are restricted to explicit files and purposes', function (): void {
    $allowed = [
        'Modules/Projects/app/Models/Project.php' => ['Tasks' => 'task relation'],
        'Modules/Projects/app/Services/ProjectMemberService.php' => ['Activity' => 'membership audit', 'Tasks' => 'assignment and watcher cleanup'],
        'Modules/Projects/app/Services/ProjectQueryService.php' => ['Activity' => 'project activity read model'],
        'Modules/Projects/app/Services/ProjectService.php' => ['Activity' => 'project audit', 'Tasks' => 'key immutability check'],
        'Modules/Tasks/app/Http/Controllers/BacklogController.php' => ['Projects' => 'route context'],
        'Modules/Tasks/app/Http/Controllers/TaskAttachmentController.php' => ['Media' => 'named media failures'],
        'Modules/Tasks/app/Http/Controllers/TaskBoardController.php' => ['Projects' => 'route context'],
        'Modules/Tasks/app/Http/Controllers/TaskController.php' => ['Projects' => 'route context'],
        'Modules/Tasks/app/Http/Controllers/TaskLabelController.php' => ['Projects' => 'route context'],
        'Modules/Tasks/app/Http/Controllers/Api/V1/BacklogController.php' => ['Projects' => 'route context'],
        'Modules/Tasks/app/Http/Controllers/Api/V1/TaskAttachmentController.php' => ['Media' => 'named media failures'],
        'Modules/Tasks/app/Http/Controllers/Api/V1/TaskBoardController.php' => ['Projects' => 'route context'],
        'Modules/Tasks/app/Http/Controllers/Api/V1/TaskLabelController.php' => ['Projects' => 'route context'],
        'Modules/Tasks/app/Models/Task.php' => ['Projects' => 'project relation'],
        'Modules/Tasks/app/Models/TaskAttachment.php' => ['Media' => 'media relation'],
        'Modules/Tasks/app/Models/TaskLabel.php' => ['Projects' => 'project relation'],
        'Modules/Tasks/app/Policies/TaskPolicy.php' => ['Projects' => 'project lifecycle and membership authorization'],
        'Modules/Tasks/app/Providers/TasksServiceProvider.php' => ['Projects' => 'scoped label route binding'],
        'Modules/Tasks/app/Repositories/Contracts/TaskLabelRepositoryInterface.php' => ['Projects' => 'project-scoped contract'],
        'Modules/Tasks/app/Repositories/Contracts/TaskRepositoryInterface.php' => ['Projects' => 'project-scoped contract'],
        'Modules/Tasks/app/Repositories/Contracts/TaskWatcherRepositoryInterface.php' => ['Projects' => 'project cleanup contract'],
        'Modules/Tasks/app/Repositories/Eloquent/EloquentTaskLabelRepository.php' => ['Projects' => 'project-scoped persistence'],
        'Modules/Tasks/app/Repositories/Eloquent/EloquentTaskRepository.php' => ['Projects' => 'visibility and project locking'],
        'Modules/Tasks/app/Repositories/Eloquent/EloquentTaskWatcherRepository.php' => ['Projects' => 'project watcher cleanup'],
        'Modules/Tasks/app/Rules/AllowedTaskAttachmentFile.php' => ['Media' => 'central media allowlist'],
        'Modules/Tasks/app/Services/BacklogQueryService.php' => ['Projects' => 'project-scoped backlog'],
        'Modules/Tasks/app/Services/QuickTaskCreateService.php' => ['Projects' => 'visible active project resolution'],
        'Modules/Tasks/app/Services/TaskAssignmentService.php' => ['Activity' => 'assignment audit', 'Projects' => 'membership and lifecycle invariants'],
        'Modules/Tasks/app/Services/TaskAttachmentService.php' => ['Activity' => 'attachment audit', 'Media' => 'binary lifecycle', 'Projects' => 'project lifecycle'],
        'Modules/Tasks/app/Services/TaskBoardQueryService.php' => ['Projects' => 'project-scoped board'],
        'Modules/Tasks/app/Services/TaskCommentService.php' => ['Activity' => 'comment audit', 'Projects' => 'project lifecycle'],
        'Modules/Tasks/app/Services/TaskLabelService.php' => ['Activity' => 'label audit', 'Projects' => 'project membership and lifecycle'],
        'Modules/Tasks/app/Services/TaskQueryService.php' => ['Activity' => 'task detail activity', 'Projects' => 'project membership read model'],
        'Modules/Tasks/app/Services/TaskRankService.php' => ['Activity' => 'rank audit', 'Projects' => 'manager and lifecycle rules'],
        'Modules/Tasks/app/Services/TaskService.php' => ['Activity' => 'task audit', 'Projects' => 'project resolution membership and issue allocation'],
        'Modules/Tasks/app/Services/TaskStatusService.php' => ['Activity' => 'status audit', 'Projects' => 'membership and lifecycle rules'],
        'Modules/Tasks/app/Services/TaskWatcherNotificationService.php' => ['Activity' => 'event-specific notification payload'],
        'Modules/Tasks/app/Services/TaskWatcherService.php' => ['Activity' => 'watcher audit', 'Projects' => 'membership and lifecycle rules'],
        'Modules/Tasks/app/Support/TaskTransitionRules.php' => ['Projects' => 'project lifecycle input'],
        'Modules/Activity/app/Http/Controllers/ActivityController.php' => ['Projects' => 'project route context', 'Tasks' => 'task route context'],
        'Modules/Activity/app/Http/Controllers/Api/V1/ActivityController.php' => ['Projects' => 'project route context', 'Tasks' => 'task route context'],
        'Modules/Activity/app/Repositories/Contracts/ActivityRepositoryInterface.php' => ['Projects' => 'scoped project activity', 'Tasks' => 'scoped task activity'],
        'Modules/Activity/app/Repositories/Eloquent/EloquentActivityRepository.php' => ['Projects' => 'project visibility', 'Tasks' => 'task visibility'],
        'Modules/Activity/app/Services/ActivityQueryService.php' => ['Projects' => 'project activity read model', 'Tasks' => 'task activity read model'],
        'Modules/Dashboard/app/Data/DashboardSummaryData.php' => ['Tasks' => 'task distribution shape'],
        'Modules/Dashboard/app/Http/Controllers/Api/V1/DashboardController.php' => ['Tasks' => 'task resource projection'],
        'Modules/Dashboard/app/Http/Resources/DashboardSummaryResource.php' => ['Tasks' => 'task enum presentation'],
        'Modules/Dashboard/app/Livewire/QuickTaskCreate.php' => ['Projects' => 'project options', 'Tasks' => 'task create use case'],
        'Modules/Dashboard/app/Services/DashboardService.php' => ['Activity' => 'recent activity', 'Projects' => 'project aggregates', 'Tasks' => 'task aggregates and queues'],
    ];
    $violations = [];

    foreach (['Projects', 'Tasks', 'Media', 'Activity', 'Dashboard'] as $module) {
        foreach (sourceFiles("Modules/{$module}/app") as $path => $source) {
            $relative = relativeSourcePath($path);
            $actual = array_values(array_filter(
                SourceGuard::moduleDependencies($source),
                fn (string $dependency): bool => $dependency !== $module,
            ));
            $expected = array_keys($allowed[$relative] ?? []);
            sort($actual);
            sort($expected);

            if ($actual !== $expected) {
                $violations[] = $relative.' expected ['.implode(', ', $expected).'] actual ['.implode(', ', $actual).']';
            }
            if (SourceGuard::resolvesModuleFromContainer($source)) {
                $violations[] = $relative.' resolves a module through the service locator';
            }
        }
    }

    expect($violations)->toBe([]);
});

test('path classification and relative comparisons are separator neutral', function (string $path): void {
    expect(SourceGuard::normalizePath($path))->toBe('Modules/Tasks/app/Http/Controllers/Api/V1/TaskController.php')
        ->and(SourceGuard::isController($path))->toBeTrue()
        ->and(SourceGuard::isApiController($path))->toBeTrue()
        ->and(SourceGuard::relativePath('C:\\repo', 'C:\\repo\\'.$path))
        ->toBe('Modules/Tasks/app/Http/Controllers/Api/V1/TaskController.php');
})->with([
    'windows' => 'Modules\\Tasks\\app\\Http\\Controllers\\Api\\V1\\TaskController.php',
    'unix' => 'Modules/Tasks/app/Http/Controllers/Api/V1/TaskController.php',
]);

test('guard mutation fixtures detect forbidden layer forms and allow transaction ownership', function (): void {
    expect(SourceGuard::controllerViolations('<?php User::find(1);'))->toContain('model static query')
        ->and(SourceGuard::controllerViolations('<?php $task->comments()->count();'))->toContain('relation terminal query')
        ->and(SourceGuard::controllerViolations('<?php use App\\Repositories\\Contracts\\UserRepositoryInterface;'))->toContain('repository dependency')
        ->and(SourceGuard::persistenceViolations('<?php $task->save();', true))->toContain('model persistence')
        ->and(SourceGuard::persistenceViolations('<?php DB::transaction(fn () => true);', true))->toBe([])
        ->and(SourceGuard::persistenceViolations('<?php DB::table("tasks")->get();', true))->toContain('query builder')
        ->and(SourceGuard::moduleDependencies('<?php use Modules\\Media\\Models\\Media; $x = new \\Modules\\Projects\\Models\\Project;'))
        ->toBe(['Media', 'Projects'])
        ->and(SourceGuard::resolvesModuleFromContainer('<?php app(Modules\\Media\\Services\\MediaStorageService::class);'))->toBeTrue();
});

test('controllers call at most one application boundary per public action', function (): void {
    $violations = [];

    foreach (array_merge(sourceFiles('app/Http/Controllers'), sourceFiles('Modules')) as $path => $source) {
        if (! SourceGuard::isController($path)) {
            continue;
        }

        foreach (SourceGuard::publicMethodBoundaryCalls($source) as $method => $boundaries) {
            if (count($boundaries) > 1) {
                $violations[] = relativeSourcePath($path).'::'.$method.' -> '.implode(', ', $boundaries);
            }
        }
    }

    expect($violations)->toBe([]);
});

test('non-repository layers contain no direct persistence and API actions declare response types', function (): void {
    $violations = [];

    foreach (array_merge(sourceFiles('app'), sourceFiles('Modules')) as $path => $source) {
        $relative = relativeSourcePath($path);
        $isService = str_contains($relative, '/Services/');
        $isRestricted = $isService
            || str_contains($relative, '/Http/Requests/')
            || str_contains($relative, '/Policies/')
            || str_contains($relative, '/Http/Middleware/')
            || str_contains($relative, '/Http/Resources/')
            || str_contains($relative, '/View/Composers/');

        if (! $isRestricted) {
            continue;
        }

        if ($relative === 'Modules/Media/app/Services/MediaStorageService.php') {
            continue; // Media owns the approved private-storage boundary.
        }

        foreach (SourceGuard::persistenceViolations($source, $isService) as $violation) {
            $violations[] = $relative.': '.$violation;
        }
    }

    expect($violations)->toBe([]);

    $apiControllers = [
        AuthenticationController::class,
        ActivityController::class,
        DashboardController::class,
        ProjectController::class,
        ProjectMemberController::class,
        BacklogController::class,
        TaskAttachmentController::class,
        TaskBoardController::class,
        TaskCommentController::class,
        TaskController::class,
        TaskLabelController::class,
        TaskWatcherController::class,
    ];

    $invalidApiControllers = array_values(array_filter(
        $apiControllers,
        fn (string $controller): bool => ! SourceGuard::hasApprovedApiActionReturnTypes($controller),
    ));

    expect($invalidApiControllers)->toBe([]);
});

test('services do not execute Eloquent queries or relationship loading directly', function () {
    $violations = [];

    foreach (array_merge(sourceFiles('app/Services'), sourceFiles('Modules')) as $path => $source) {
        if (! str_contains($path, '/Services/')) {
            continue;
        }

        if (preg_match('/\b[A-Z][A-Za-z0-9_]*::query\(|->load(?:Missing)?\(|\$(?:user|task|project|label|comment|attachment|membership|row|token)->(?:save|delete|refresh|paginate)\(|->(?:notifications|unreadNotifications|tokens|labels|watchers|subtasks|memberships)\(\)/', $source, $match)) {
            $violations[] = relativeSourcePath($path).': '.$match[0];
        }
    }

    expect($violations)->toBe([]);
});

test('repository contracts do not expose Eloquent builders and implementations follow the expected layout', function () {
    $contracts = array_merge(sourceFiles('app/Repositories/Contracts'), sourceFiles('Modules'));
    $violations = [];

    foreach ($contracts as $path => $source) {
        if (! str_contains($path, '/Repositories/Contracts/')) {
            continue;
        }
        if (! str_ends_with($path, 'RepositoryInterface.php') || str_contains($source, 'Eloquent\\Builder')) {
            $violations[] = relativeSourcePath($path);
        }
    }

    foreach (array_merge(sourceFiles('app/Repositories/Eloquent'), sourceFiles('Modules')) as $path => $source) {
        if (! str_contains($path, '/Repositories/Eloquent/')) {
            continue;
        }
        if (! str_contains($source, 'implements ') || ! str_contains($source, 'RepositoryInterface')) {
            $violations[] = relativeSourcePath($path);
        }
    }

    expect($violations)->toBe([]);
});

test('resources and views do not trigger implicit role token or relation loading', function () {
    $violations = [];

    foreach (array_merge(sourceFiles('app/Http/Resources'), sourceFiles('resources/views'), sourceFiles('Modules')) as $path => $source) {
        if (! str_contains($path, '/Http/Resources/') && ! str_contains($path, '/resources/views/')) {
            continue;
        }
        if (preg_match('/->load(?:Missing)?\(|->getRoleNames\(|->currentAccessToken\(|::query\(/', $source, $match)) {
            $violations[] = relativeSourcePath($path).': '.$match[0];
        }
    }

    expect($violations)->toBe([]);
});

test('dashboard application composition consumes prepared read results only', function () {
    $source = file_get_contents(base_path('Modules/Dashboard/app/Services/DashboardService.php'));

    expect($source)
        ->not->toContain('Eloquent\\Builder', '::query(', 'DB::', '->load(', '->loadMissing(');
});

test('read filter adapters stay typed scoped and free of global existence checks', function () {
    $requestFiles = [
        'Modules/Tasks/app/Http/Requests/TaskFilterRequest.php',
        'Modules/Tasks/app/Http/Requests/TaskIndexRequest.php',
        'Modules/Tasks/app/Http/Requests/TaskReadModelRequest.php',
        'Modules/Tasks/app/Http/Requests/Api/V1/TaskIndexRequest.php',
        'Modules/Tasks/app/Http/Requests/Api/V1/TaskReadModelRequest.php',
        'Modules/Activity/app/Http/Requests/ActivityFilterRequest.php',
        'Modules/Activity/app/Http/Requests/ActivityIndexRequest.php',
        'Modules/Activity/app/Http/Requests/Api/V1/ActivityIndexRequest.php',
    ];

    foreach ($requestFiles as $file) {
        expect(file_get_contents(base_path($file)), $file)->not->toContain('exists:');
    }

    expect(file_get_contents(base_path('Modules/Tasks/app/Data/TaskFiltersData.php')))
        ->toContain('CarbonImmutable');
    expect(file_get_contents(base_path('Modules/Activity/app/Data/ActivityFiltersData.php')))
        ->toContain('CarbonImmutable', 'ActivityEvent');
    expect(file_get_contents(base_path('Modules/Activity/app/Repositories/Contracts/ActivityRepositoryInterface.php')))
        ->toContain('ActivityFiltersData')
        ->not->toContain('array $filters');
    expect(file_get_contents(base_path('Modules/Activity/app/Http/Controllers/ActivityController.php')))
        ->toContain('ActivityIndexRequest')
        ->not->toContain('Illuminate\\Http\\Request');
});

test('all relation requests defer existence and actor scope to application boundaries', function () {
    $violations = [];

    foreach (array_merge(sourceFiles('app/Http/Requests'), sourceFiles('Modules')) as $path => $source) {
        if (! str_contains($path, '/Http/Requests/')) {
            continue;
        }

        if (str_contains($source, 'exists:')) {
            $violations[] = relativeSourcePath($path);
        }
    }

    expect($violations)->toBe([]);
});

test('application services do not throw HTTP validation exceptions and Livewire does not catch generic failures', function () {
    $serviceViolations = [];
    $livewireViolations = [];

    foreach (array_merge(sourceFiles('app/Services'), sourceFiles('Modules')) as $path => $source) {
        if (str_contains($path, '/app/Services/') && str_contains($source, 'Illuminate\\Validation\\ValidationException')) {
            $serviceViolations[] = relativeSourcePath($path);
        }
        if (str_contains($path, '/app/Livewire/')
            && preg_match('/catch\s*\([^)]*(?:LogicException|Throwable|(?<!Validation)Exception)[^)]*\)/', $source, $match)) {
            $livewireViolations[] = relativeSourcePath($path).': '.$match[0];
        }
    }

    expect($serviceViolations)->toBe([])
        ->and($livewireViolations)->toBe([]);
});

test('login request validates shape only and active middleware does not refresh the actor', function () {
    $request = file_get_contents(base_path('app/Http/Requests/Auth/LoginRequest.php'));
    $middleware = file_get_contents(base_path('app/Http/Middleware/EnsureActiveUser.php'));

    expect($request)
        ->not->toContain('Auth::', 'RateLimiter::', 'authenticate(', 'ensureIsNotRateLimited')
        ->and($middleware)
        ->not->toContain('->fresh(', '::query(');
});
