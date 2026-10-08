<?php

use Illuminate\Support\Facades\Event;
use Modules\LearningCatalog\Contracts\PublishedLearningEntryFeed;
use Modules\LearningCatalog\Events\LearningEntryPublished;
use Modules\LearningCatalog\Repositories\Contracts\LearningEntryRepositoryInterface;
use Modules\LearningInsights\Repositories\Contracts\LearningInsightRepositoryInterface;
use Tests\Architecture\Support\LearningBoundaryGuard;
use Tests\TestCase;

uses(TestCase::class);

/** @return array<string, string> */
function r1LearningSources(string $directory, bool $runtimeOnly = true): array
{
    $files = [];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path($directory))) as $file) {
        if (! $file->isFile()) {
            continue;
        }

        $path = str_replace('\\', '/', $file->getPathname());

        if ($runtimeOnly && str_contains($path, '/tests/')) {
            continue;
        }

        $files[$path] = $file->getExtension() === 'php' ? file_get_contents($path) : '';
    }

    return $files;
}

test('both learning modules are registered and their public feed and listener are wired', function (): void {
    $statuses = json_decode(file_get_contents(base_path('modules_statuses.json')), true, flags: JSON_THROW_ON_ERROR);

    foreach (LearningBoundaryGuard::MODULES as $module) {
        expect(is_dir(base_path('Modules/'.$module.'/app')), $module)->toBeTrue();
        $metadata = json_decode(file_get_contents(base_path('Modules/'.$module.'/module.json')), true, flags: JSON_THROW_ON_ERROR);

        expect($statuses[$module] ?? false, $module)->toBeTrue()
            ->and($metadata['name'])->toBe($module)
            ->and($metadata['providers'])->toContain('Modules\\'.$module.'\\Providers\\'.$module.'ServiceProvider');
    }

    foreach ([PublishedLearningEntryFeed::class, LearningEntryRepositoryInterface::class, LearningInsightRepositoryInterface::class] as $contract) {
        expect(app()->bound($contract), $contract)->toBeTrue()
            ->and(app($contract))->toBeInstanceOf($contract);
    }

    expect(Event::hasListeners(LearningEntryPublished::class))->toBeTrue();
});

test('learning modules use only their allowed dependencies tables and layers without HTTP or UI', function (): void {
    $violations = [];

    foreach (LearningBoundaryGuard::MODULES as $module) {
        $sources = r1LearningSources('Modules/'.$module);
        expect(count($sources), $module.' source inventory')->toBeGreaterThan(0);

        foreach ($sources as $path => $source) {
            foreach (array_merge(
                LearningBoundaryGuard::dependencyViolations($module, $source),
                LearningBoundaryGuard::tableViolations($module, $source),
                LearningBoundaryGuard::layerViolations($path, $source),
                LearningBoundaryGuard::uiViolations($path, $source),
            ) as $violation) {
                $violations[] = $path.': '.$violation;
            }
        }
    }

    expect($violations)->toBe([]);
});

test('production modules and host business sources do not depend on the learning lab', function (): void {
    $violations = [];

    foreach (['app', 'routes', 'database', 'Modules/Projects', 'Modules/Tasks', 'Modules/Media', 'Modules/Activity', 'Modules/Dashboard'] as $directory) {
        foreach (r1LearningSources($directory) as $path => $source) {
            foreach (array_merge(
                LearningBoundaryGuard::dependencyViolations(null, $source),
                LearningBoundaryGuard::tableViolations(null, $source),
            ) as $violation) {
                $violations[] = $path.': '.$violation;
            }
        }
    }

    expect($violations)->toBe([]);
});

test('learning dependency guard permits the exact public API and owned classes', function (string $owner, string $source): void {
    expect(LearningBoundaryGuard::dependencyViolations($owner, $source))->toBe([]);
})->with([
    'catalog owns its repository' => ['LearningCatalog', '<?php use Modules\\LearningCatalog\\Repositories\\Contracts\\LearningEntryRepositoryInterface;'],
    'insights owns its model' => ['LearningInsights', '<?php use Modules\\LearningInsights\\Models\\LearningEntryInsight;'],
    'framework and PHP types' => ['LearningCatalog', '<?php use Illuminate\\Support\\Facades\\DB; use DateTimeImmutable;'],
    'exact public contract alias' => ['LearningInsights', '<?php use Modules\\LearningCatalog\\Contracts\\PublishedLearningEntryFeed as Feed; function read(Feed $feed) {}'],
    'all three public grouped imports' => ['LearningInsights', '<?php use Modules\\LearningCatalog\\{Contracts\\PublishedLearningEntryFeed as Feed, Data\\PublishedLearningEntryData, Events\\LearningEntryPublished};'],
    'fully qualified public data' => ['LearningInsights', '<?php $entry = new \\Modules\\LearningCatalog\\Data\\PublishedLearningEntryData(1, "title", $date);'],
    'quoted public contract' => ['LearningInsights', '<?php $contract = "Modules\\\\LearningCatalog\\\\Contracts\\\\PublishedLearningEntryFeed";'],
    'comments have no runtime dependency' => ['LearningInsights', '<?php /* use Modules\\LearningCatalog\\Models\\LearningEntry; */'],
]);

test('learning dependency guard rejects internal classes and namespace alias bypasses', function (string $owner, string $source): void {
    expect(LearningBoundaryGuard::dependencyViolations($owner, $source))->not->toBe([]);
})->with([
    'producer must not know consumer' => ['LearningCatalog', '<?php use Modules\\LearningInsights\\Services\\RebuildLearningInsightsService;'],
    'consumer cannot import catalog model' => ['LearningInsights', '<?php use Modules\\LearningCatalog\\Models\\LearningEntry;'],
    'consumer cannot import catalog repository' => ['LearningInsights', '<?php use Modules\\LearningCatalog\\Repositories\\Contracts\\LearningEntryRepositoryInterface;'],
    'consumer cannot import catalog service' => ['LearningInsights', '<?php use Modules\\LearningCatalog\\Services\\LearningEntryService;'],
    'consumer cannot import write input DTO' => ['LearningInsights', '<?php use Modules\\LearningCatalog\\Data\\PublishLearningEntryData;'],
    'contract wildcard is not a public allowlist' => ['LearningInsights', '<?php use Modules\\LearningCatalog\\Contracts\\UnapprovedWriteContract;'],
    'catalog namespace alias' => ['LearningInsights', '<?php use Modules\\LearningCatalog as Catalog; new Catalog\\Models\\LearningEntry;'],
    'root namespace alias' => ['LearningInsights', '<?php use Modules as ModulesAlias; new ModulesAlias\\LearningCatalog\\Models\\LearningEntry;'],
    'exact internal class alias' => ['LearningInsights', '<?php use Modules\\LearningCatalog\\Models\\LearningEntry as Entry; Entry::query();'],
    'grouped internal class alias' => ['LearningInsights', '<?php use Modules\\LearningCatalog\\{Data\\PublishedLearningEntryData, Models\\LearningEntry as Entry}; new Entry;'],
    'fully qualified constructor' => ['LearningInsights', '<?php new \\Modules\\LearningCatalog\\Models\\LearningEntry;'],
    'fully qualified container resolution' => ['LearningInsights', '<?php app(\\Modules\\LearningCatalog\\Services\\LearningEntryService::class);'],
    'quoted single slash container reference' => ['LearningInsights', '<?php resolve(\'Modules\\LearningCatalog\\Services\\LearningEntryService\');'],
    'quoted escaped container reference' => ['LearningInsights', '<?php $container->make("Modules\\\\LearningCatalog\\\\Services\\\\LearningEntryService");'],
    'learning cannot consume host account' => ['LearningCatalog', '<?php use App\\Models\\User;'],
    'learning cannot consume host service' => ['LearningInsights', '<?php new \\App\\Services\\AuthenticationService;'],
    'learning cannot change its namespace to production' => ['LearningInsights', '<?php namespace Modules\\Tasks\\Services;'],
    ...array_reduce(['Projects', 'Tasks', 'Media', 'Activity', 'Dashboard'], function (array $fixtures, string $module): array {
        foreach (LearningBoundaryGuard::MODULES as $owner) {
            $fixtures[$owner.' cannot consume '.$module] = [$owner, '<?php use Modules\\'.$module.'\\Models\\Example;'];
        }

        return $fixtures;
    }, []),
]);

test('reverse dependency guard also catches host and production aliases and strings', function (string $source): void {
    expect(LearningBoundaryGuard::dependencyViolations(null, $source))->not->toBe([]);
})->with([
    'host imports Catalog service' => '<?php namespace App\\Services; use Modules\\LearningCatalog\\Services\\LearningEntryService;',
    'production imports Insights' => '<?php namespace Modules\\Tasks\\Services; use Modules\\LearningInsights\\Services\\RebuildLearningInsightsService;',
    'production uses namespace alias' => '<?php use Modules\\LearningInsights as Insights; new Insights\\Services\\RebuildLearningInsightsService;',
    'production uses grouped import' => '<?php use Modules\\LearningCatalog\\{Contracts\\PublishedLearningEntryFeed};',
    'host resolves quoted contract' => '<?php app(\'Modules\\LearningCatalog\\Contracts\\PublishedLearningEntryFeed\');',
    'production uses fully qualified event' => '<?php new \\Modules\\LearningCatalog\\Events\\LearningEntryPublished;',
]);

test('learning table guard permits literal owned tables and normal model writes', function (string $owner, string $source): void {
    expect(LearningBoundaryGuard::tableViolations($owner, $source))->toBe([]);
})->with([
    'Catalog migration' => ['LearningCatalog', '<?php Schema::create("r1_learning_entries", function ($table) {});'],
    'Insights query' => ['LearningInsights', '<?php DB::table("r1_learning_insight_entries")->get();'],
    'Insights model table' => ['LearningInsights', '<?php protected $table = "r1_learning_insight_entries";'],
    'model attribute create is not schema create' => ['LearningCatalog', '<?php LearningEntry::query()->create(["title" => "Module Boundary"]);'],
]);

test('learning table guard rejects cross module tables raw SQL and unbounded table names', function (string $owner, string $source): void {
    expect(LearningBoundaryGuard::tableViolations($owner, $source))->not->toBe([]);
})->with([
    'Insights reads Catalog table' => ['LearningInsights', '<?php DB::table("r1_learning_entries")->get();'],
    'Catalog reads Insights table' => ['LearningCatalog', '<?php DB::table("r1_learning_insight_entries")->get();'],
    'learning reads production table' => ['LearningCatalog', '<?php DB::table("tasks")->get();'],
    'schema alias creates production table' => ['LearningInsights', '<?php use Illuminate\\Support\\Facades\\Schema as Ddl; Ddl::create("users", function ($table) {});'],
    'join bypass' => ['LearningInsights', '<?php $query->join("r1_learning_entries", "entry_id", "id");'],
    'model table bypass' => ['LearningInsights', '<?php protected $table = "r1_learning_entries";'],
    'foreign key target' => ['LearningInsights', '<?php $table->foreign("entry_id")->references("id")->on("r1_learning_entries");'],
    'implicit foreign key target' => ['LearningCatalog', '<?php $table->foreignId("user_id")->constrained();'],
    'raw SQL via facade alias' => ['LearningInsights', '<?php use Illuminate\\Support\\Facades\\DB as Database; Database::select("select * from r1_learning_entries");'],
    'dynamic table' => ['LearningInsights', '<?php DB::table($tableName)->get();'],
]);

test('production table guard rejects direct access to either learning table', function (string $table): void {
    expect(LearningBoundaryGuard::tableViolations(null, '<?php DB::table("'.$table.'")->get();'))->not->toBe([]);
})->with(['r1_learning_entries', 'r1_learning_insight_entries']);

test('learning layer guard allows orchestration and blocks persistence bypasses', function (): void {
    $service = 'Modules/LearningCatalog/app/Services/LearningEntryService.php';
    $listener = 'Modules/LearningInsights/app/Listeners/RecordPublishedLearningEntry.php';
    $contract = 'Modules/LearningInsights/app/Repositories/Contracts/LearningInsightRepositoryInterface.php';
    $repository = 'Modules/LearningInsights/app/Repositories/Eloquent/EloquentLearningInsightRepository.php';

    expect(LearningBoundaryGuard::layerViolations($service, '<?php DB::transaction(fn () => $this->entries->createPublished($data));'))->toBe([])
        ->and(LearningBoundaryGuard::layerViolations($listener, '<?php $this->insights->recordPublishedIfMissing($event);'))->toBe([])
        ->and(LearningBoundaryGuard::layerViolations($service, '<?php LearningEntry::query()->get();'))->not->toBe([])
        ->and(LearningBoundaryGuard::layerViolations($service, '<?php $entry->save();'))->not->toBe([])
        ->and(LearningBoundaryGuard::layerViolations($service, '<?php use Illuminate\\Support\\Facades\\DB as Database; Database::table("r1_learning_entries")->get();'))->not->toBe([])
        ->and(LearningBoundaryGuard::layerViolations($listener, '<?php DB::transaction(fn () => true);'))->not->toBe([])
        ->and(LearningBoundaryGuard::layerViolations($listener, '<?php LearningEntryInsight::create([]);'))->not->toBe([])
        ->and(LearningBoundaryGuard::layerViolations($contract, '<?php use Illuminate\\Database\\Eloquent\\Builder as Query; interface Example { public function all(): Query; }'))->not->toBe([])
        ->and(LearningBoundaryGuard::layerViolations($contract, '<?php public function all(): \\Illuminate\\Database\\Query\\Builder;'))->not->toBe([])
        ->and(LearningBoundaryGuard::layerViolations($repository, '<?php DB::transaction(fn () => true);'))->not->toBe([]);
});

test('learning UI guard rejects files and direct provider registration including aliases', function (string $path, string $source): void {
    expect(LearningBoundaryGuard::uiViolations($path, $source))->not->toBe([]);
})->with([
    'route file' => ['Modules/LearningCatalog/routes/web.php', '<?php'],
    'Windows controller path' => ['Modules\\LearningCatalog\\app\\Http\\Controllers\\LearningController.php', '<?php'],
    'Livewire component' => ['Modules/LearningCatalog/app/Livewire/LearningEntryForm.php', '<?php'],
    'Blade view' => ['Modules/LearningInsights/resources/views/index.blade.php', ''],
    'provider direct Route call' => ['Modules/LearningCatalog/app/Providers/LearningCatalogServiceProvider.php', '<?php Route::get("/learning", fn () => "hello");'],
    'provider Route alias' => ['Modules/LearningCatalog/app/Providers/LearningCatalogServiceProvider.php', '<?php use Illuminate\\Support\\Facades\\Route as Routes; Routes::get("/learning", fn () => "hello");'],
    'provider fully qualified Route' => ['Modules/LearningCatalog/app/Providers/LearningCatalogServiceProvider.php', '<?php \\Illuminate\\Support\\Facades\\Route::get("/learning", fn () => "hello");'],
    'provider route loader' => ['Modules/LearningCatalog/app/Providers/LearningCatalogServiceProvider.php', '<?php $this->loadRoutesFrom("routes/web.php");'],
    'provider resolves router' => ['Modules/LearningCatalog/app/Providers/LearningCatalogServiceProvider.php', '<?php app("router")->get("/learning", fn () => "hello");'],
]);
