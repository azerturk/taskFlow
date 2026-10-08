<?php

use Illuminate\Database\Events\TransactionCommitting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\LearningCatalog\Contracts\PublishedLearningEntryFeed;
use Modules\LearningCatalog\Data\PublishLearningEntryData;
use Modules\LearningCatalog\Events\LearningEntryPublished;
use Modules\LearningCatalog\Models\LearningEntry;
use Modules\LearningCatalog\Repositories\Contracts\LearningEntryRepositoryInterface;
use Modules\LearningCatalog\Repositories\Eloquent\EloquentLearningEntryRepository;
use Modules\LearningCatalog\Services\LearningEntryService;
use Modules\LearningInsights\Models\LearningEntryInsight;
use Modules\LearningInsights\Repositories\Contracts\LearningInsightRepositoryInterface;
use Modules\LearningInsights\Repositories\Eloquent\EloquentLearningInsightRepository;
use Modules\LearningInsights\Services\RebuildLearningInsightsService;

test('real publish commits Catalog before its registered listener creates the matching Insights projection', function (): void {
    expect(DB::transactionLevel())->toBe(0);
    $observedEvents = [];
    $projectionsBeforeCommit = [];
    Event::listen(TransactionCommitting::class, function (TransactionCommitting $event) use (&$projectionsBeforeCommit): void {
        if ($event->connection->transactionLevel() === 1) {
            $projectionsBeforeCommit[] = DB::table('r1_learning_insight_entries')->count();
        }
    });
    Event::listen(LearningEntryPublished::class, function (LearningEntryPublished $event) use (&$observedEvents): void {
        expect(DB::transactionLevel())->toBe(0)
            ->and(DB::connection()->getPdo()->inTransaction())->toBeFalse();
        $observedEvents[] = $event;
    });

    $entry = app(LearningEntryService::class)->publish(new PublishLearningEntryData('Public API and Events'));
    $projection = LearningEntryInsight::query()->sole();

    expect($projectionsBeforeCommit)->toBe([0])
        ->and($observedEvents)->toHaveCount(1)
        ->and(Str::isUuid($observedEvents[0]->eventId))->toBeTrue()
        ->and($observedEvents[0]->entryId)->toBe($entry->id)
        ->and($observedEvents[0]->title)->toBe($entry->title)
        ->and($observedEvents[0]->publishedAt->getTimestamp())->toBe($entry->published_at->getTimestamp())
        ->and($projection->entry_id)->toBe($entry->id)
        ->and($projection->source_event_id)->toBe($observedEvents[0]->eventId)
        ->and($projection->title)->toBe($entry->title)
        ->and($projection->published_at->equalTo($entry->published_at))->toBeTrue()
        ->and(DB::table('r1_learning_entries')->count())->toBe(1)
        ->and(DB::transactionLevel())->toBe(0);
});

test('a publish inside an outer transaction waits for the real outer commit before invoking listeners', function (): void {
    $observedEvents = [];
    Event::listen(LearningEntryPublished::class, function (LearningEntryPublished $event) use (&$observedEvents): void {
        expect(DB::transactionLevel())->toBe(0);
        $observedEvents[] = $event;
    });

    DB::beginTransaction();

    try {
        $entry = app(LearningEntryService::class)->publish(new PublishLearningEntryData('Wait for outer commit'));

        expect(DB::transactionLevel())->toBe(1)
            ->and(DB::connection()->getPdo()->inTransaction())->toBeTrue()
            ->and(DB::table('r1_learning_entries')->count())->toBe(1)
            ->and(DB::table('r1_learning_insight_entries')->count())->toBe(0)
            ->and($observedEvents)->toBe([]);

        DB::commit();

        expect(DB::transactionLevel())->toBe(0)
            ->and($observedEvents)->toHaveCount(1)
            ->and(DB::table('r1_learning_insight_entries')->where('entry_id', $entry->id)->count())->toBe(1);
    } finally {
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
    }
});

test('an outer rollback drops the Catalog row and deferred event without leaking it into a later commit', function (): void {
    $observedEvents = [];
    Event::listen(LearningEntryPublished::class, function (LearningEntryPublished $event) use (&$observedEvents): void {
        $observedEvents[] = $event;
    });

    expect(fn () => DB::transaction(function (): void {
        app(LearningEntryService::class)->publish(new PublishLearningEntryData('Rolled back entry'));

        expect(DB::table('r1_learning_insight_entries')->count())->toBe(0);

        throw new RuntimeException('Outer operation failed');
    }))->toThrow(RuntimeException::class, 'Outer operation failed');

    expect(DB::transactionLevel())->toBe(0)
        ->and(DB::table('r1_learning_entries')->count())->toBe(0)
        ->and(DB::table('r1_learning_insight_entries')->count())->toBe(0)
        ->and($observedEvents)->toBe([]);

    $entry = app(LearningEntryService::class)->publish(new PublishLearningEntryData('Later committed entry'));

    expect($observedEvents)->toHaveCount(1)
        ->and($observedEvents[0]->title)->toBe('Later committed entry')
        ->and(DB::table('r1_learning_entries')->count())->toBe(1)
        ->and(DB::table('r1_learning_insight_entries')->where('entry_id', $entry->id)->count())->toBe(1);
});

test('a repository failure after a real Catalog insert rolls back the write and never invokes a listener', function (): void {
    $observedEvents = [];
    Event::listen(LearningEntryPublished::class, function (LearningEntryPublished $event) use (&$observedEvents): void {
        $observedEvents[] = $event;
    });
    $repository = new class implements LearningEntryRepositoryInterface
    {
        public function createPublished(PublishLearningEntryData $data): LearningEntry
        {
            (new EloquentLearningEntryRepository)->createPublished($data);

            expect(DB::table('r1_learning_entries')->count())->toBe(1);

            throw new RuntimeException('Failure after real Catalog insert');
        }

        public function allPublished(): array
        {
            return (new EloquentLearningEntryRepository)->allPublished();
        }
    };

    expect(fn () => (new LearningEntryService($repository))->publish(new PublishLearningEntryData('Must roll back')))
        ->toThrow(RuntimeException::class, 'Failure after real Catalog insert');

    expect(DB::table('r1_learning_entries')->count())->toBe(0)
        ->and(DB::table('r1_learning_insight_entries')->count())->toBe(0)
        ->and($observedEvents)->toBe([])
        ->and(DB::transactionLevel())->toBe(0);
});

test('Catalog publishes without a listener and the real public feed restores only the missing projections', function (): void {
    Event::forget(LearningEntryPublished::class);
    $first = app(LearningEntryService::class)->publish(new PublishLearningEntryData('No consumer required'));
    $second = app(LearningEntryService::class)->publish(new PublishLearningEntryData('Recover from real feed'));

    expect(DB::table('r1_learning_entries')->count())->toBe(2)
        ->and(DB::table('r1_learning_insight_entries')->count())->toBe(0)
        ->and(array_column(app(PublishedLearningEntryFeed::class)->all(), 'id'))->toBe([$first->id, $second->id]);

    app(RebuildLearningInsightsService::class)->rebuild();
    $originalRows = DB::table('r1_learning_insight_entries')->orderBy('entry_id')->get()->toArray();
    app(RebuildLearningInsightsService::class)->rebuild();

    expect(DB::table('r1_learning_insight_entries')->count())->toBe(2)
        ->and(DB::table('r1_learning_insight_entries')->whereNotNull('source_event_id')->count())->toBe(0)
        ->and(DB::table('r1_learning_insight_entries')->orderBy('entry_id')->get()->toArray())->toEqual($originalRows)
        ->and(DB::table('r1_learning_entries')->count())->toBe(2);

    foreach ([$first, $second] as $entry) {
        $projection = LearningEntryInsight::query()->where('entry_id', $entry->id)->sole();

        expect($projection->title)->toBe($entry->title)
            ->and($projection->published_at->equalTo($entry->published_at))->toBeTrue();
    }
});

test('a registered listener failure remains visible while committed Catalog data can be recovered through its real public feed', function (): void {
    app()->instance(LearningInsightRepositoryInterface::class, new class implements LearningInsightRepositoryInterface
    {
        public function recordPublishedIfMissing(int $entryId, ?string $sourceEventId, string $title, DateTimeImmutable $publishedAt): void
        {
            expect(DB::transactionLevel())->toBe(0);

            throw new RuntimeException('Projection persistence unavailable');
        }
    });

    expect(fn () => app(LearningEntryService::class)->publish(new PublishLearningEntryData('Committed despite listener failure')))
        ->toThrow(RuntimeException::class, 'Projection persistence unavailable');

    expect(DB::transactionLevel())->toBe(0)
        ->and(DB::connection()->getPdo()->inTransaction())->toBeFalse()
        ->and(DB::table('r1_learning_entries')->count())->toBe(1)
        ->and(DB::table('r1_learning_insight_entries')->count())->toBe(0);

    $entry = app(PublishedLearningEntryFeed::class)->all()[0];
    app()->instance(LearningInsightRepositoryInterface::class, new EloquentLearningInsightRepository);
    app(RebuildLearningInsightsService::class)->rebuild();
    app(RebuildLearningInsightsService::class)->rebuild();
    $projection = LearningEntryInsight::query()->sole();

    expect(DB::table('r1_learning_entries')->count())->toBe(1)
        ->and($projection->entry_id)->toBe($entry->id)
        ->and($projection->title)->toBe($entry->title)
        ->and($projection->published_at->getTimestamp())->toBe($entry->publishedAt->getTimestamp())
        ->and($projection->source_event_id)->toBeNull();
});

test('a rebuild second write failure rolls back its new projections and preserves previous data', function (): void {
    Event::forget(LearningEntryPublished::class);
    app(LearningEntryService::class)->publish(new PublishLearningEntryData('First missing projection'));
    app(LearningEntryService::class)->publish(new PublishLearningEntryData('Second missing projection'));
    (new EloquentLearningInsightRepository)->recordPublishedIfMissing(
        entryId: 9999,
        sourceEventId: (string) Str::uuid(),
        title: 'Existing projection outside feed',
        publishedAt: new DateTimeImmutable('2026-10-01T10:00:00+00:00'),
    );
    $original = (array) DB::table('r1_learning_insight_entries')->sole();
    $repository = new class implements LearningInsightRepositoryInterface
    {
        private int $writes = 0;

        public function recordPublishedIfMissing(int $entryId, ?string $sourceEventId, string $title, DateTimeImmutable $publishedAt): void
        {
            expect(DB::transactionLevel())->toBe(1);
            (new EloquentLearningInsightRepository)->recordPublishedIfMissing($entryId, $sourceEventId, $title, $publishedAt);

            if (++$this->writes === 2) {
                expect(DB::table('r1_learning_insight_entries')->count())->toBe(3);

                throw new RuntimeException('Second rebuild write failed');
            }
        }
    };
    $service = new RebuildLearningInsightsService(app(PublishedLearningEntryFeed::class), $repository);

    expect(fn () => $service->rebuild())->toThrow(RuntimeException::class, 'Second rebuild write failed');

    expect(DB::transactionLevel())->toBe(0)
        ->and(DB::table('r1_learning_insight_entries')->count())->toBe(1)
        ->and((array) DB::table('r1_learning_insight_entries')->sole())->toBe($original)
        ->and(DB::table('r1_learning_entries')->count())->toBe(2);

    app(RebuildLearningInsightsService::class)->rebuild();

    expect(DB::table('r1_learning_insight_entries')->count())->toBe(3)
        ->and(DB::table('r1_learning_insight_entries')->whereNull('source_event_id')->count())->toBe(2)
        ->and((array) DB::table('r1_learning_insight_entries')->where('entry_id', 9999)->sole())->toBe($original);
});

test('the two R1 migrations create and roll back their own tables and unique indexes without touching production tables', function (): void {
    expect(DB::transactionLevel())->toBe(0);
    $catalogMigration = require module_path('LearningCatalog', 'database/migrations/2026_10_03_000000_create_r1_learning_entries_table.php');
    $insightsMigration = require module_path('LearningInsights', 'database/migrations/2026_10_03_000001_create_r1_learning_insight_entries_table.php');

    $insightsMigration->down();
    $catalogMigration->down();

    expect(Schema::hasTable('r1_learning_entries'))->toBeFalse()
        ->and(Schema::hasTable('r1_learning_insight_entries'))->toBeFalse()
        ->and(Schema::hasTable('users'))->toBeTrue()
        ->and(Schema::hasTable('tasks'))->toBeTrue();

    $catalogMigration->up();
    $insightsMigration->up();
    $indexes = collect(Schema::getIndexes('r1_learning_insight_entries'));

    expect(Schema::hasTable('r1_learning_entries'))->toBeTrue()
        ->and(Schema::hasTable('r1_learning_insight_entries'))->toBeTrue()
        ->and($indexes->firstWhere('name', 'r1_learning_insight_entries_entry_id_unique')['unique'])->toBeTrue()
        ->and($indexes->firstWhere('name', 'r1_learning_insight_entries_source_event_id_unique')['unique'])->toBeTrue()
        ->and(Schema::getForeignKeys('r1_learning_insight_entries'))->toBe([])
        ->and(Schema::hasTable('users'))->toBeTrue()
        ->and(Schema::hasTable('tasks'))->toBeTrue();

    app(LearningEntryService::class)->publish(new PublishLearningEntryData('Fresh R1 tables work'));

    expect(DB::table('r1_learning_entries')->count())->toBe(1)
        ->and(DB::table('r1_learning_insight_entries')->count())->toBe(1);
});
