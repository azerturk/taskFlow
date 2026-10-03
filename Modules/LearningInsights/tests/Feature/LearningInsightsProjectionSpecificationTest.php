<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\LearningCatalog\Contracts\PublishedLearningEntryFeed;
use Modules\LearningCatalog\Data\PublishedLearningEntryData;
use Modules\LearningCatalog\Events\LearningEntryPublished;
use Modules\LearningInsights\Listeners\RecordPublishedLearningEntry;
use Modules\LearningInsights\Services\RebuildLearningInsightsService;

function publishedLearningEntryEvent(int $entryId, ?string $eventId = null, string $title = 'Module Boundary'): LearningEntryPublished
{
    return new LearningEntryPublished(
        eventId: $eventId ?? (string) Str::uuid(),
        entryId: $entryId,
        title: $title,
        publishedAt: new DateTimeImmutable('2026-10-02T10:00:00+04:00'),
    );
}

/** @param list<PublishedLearningEntryData> $entries */
function bindPublishedLearningEntryFeed(array $entries): void
{
    app()->instance(PublishedLearningEntryFeed::class, new class($entries) implements PublishedLearningEntryFeed
    {
        /** @param list<PublishedLearningEntryData> $entries */
        public function __construct(private readonly array $entries) {}

        public function all(): array
        {
            return $this->entries;
        }
    });
}

test('RecordPublishedLearningEntry creates one Insights projection from a published event', function (): void {
    $event = publishedLearningEntryEvent(101);

    app(RecordPublishedLearningEntry::class)->handle($event);

    expect(DB::table('r1_learning_insight_entries')->where('entry_id', 101)->count())->toBe(1);
});

test('handling the same event ID twice leaves exactly one Insights projection', function (): void {
    $event = publishedLearningEntryEvent(102, 'event-duplicate-102');
    $listener = app(RecordPublishedLearningEntry::class);

    $listener->handle($event);
    $listener->handle($event);

    expect(DB::table('r1_learning_insight_entries')->where('source_event_id', 'event-duplicate-102')->count())->toBe(1)
        ->and(DB::table('r1_learning_insight_entries')->count())->toBe(1);
});

test('entry ID uniqueness prevents multiple Insights projections for the same Catalog entry', function (): void {
    $listener = app(RecordPublishedLearningEntry::class);

    $listener->handle(publishedLearningEntryEvent(103, 'event-first-103', 'Original title'));
    $listener->handle(publishedLearningEntryEvent(103, 'event-second-103', 'Changed title'));

    expect(DB::table('r1_learning_insight_entries')->where('entry_id', 103)->count())->toBe(1);
});

test('rebuild creates missing Insights projections from the Catalog public feed', function (): void {
    bindPublishedLearningEntryFeed([
        new PublishedLearningEntryData(201, 'Contract', new DateTimeImmutable('2026-10-02T10:00:00+04:00')),
        new PublishedLearningEntryData(202, 'Event', new DateTimeImmutable('2026-10-02T11:00:00+04:00')),
    ]);

    app(RebuildLearningInsightsService::class)->rebuild();

    expect(DB::table('r1_learning_insight_entries')->whereIn('entry_id', [201, 202])->count())->toBe(2);
});

test('rebuild-created projections have no source event ID', function (): void {
    bindPublishedLearningEntryFeed([
        new PublishedLearningEntryData(203, 'No synthetic event', new DateTimeImmutable('2026-10-02T12:00:00+04:00')),
    ]);

    app(RebuildLearningInsightsService::class)->rebuild();

    expect(DB::table('r1_learning_insight_entries')->where('entry_id', 203)->value('source_event_id'))->toBeNull();
});

test('running rebuild repeatedly is idempotent', function (): void {
    bindPublishedLearningEntryFeed([
        new PublishedLearningEntryData(204, 'Idempotent rebuild', new DateTimeImmutable('2026-10-02T13:00:00+04:00')),
    ]);
    $rebuild = app(RebuildLearningInsightsService::class);

    $rebuild->rebuild();
    $rebuild->rebuild();

    expect(DB::table('r1_learning_insight_entries')->where('entry_id', 204)->count())->toBe(1);
});

test('rebuild neither deletes nor replaces existing Insights projections', function (): void {
    DB::table('r1_learning_insight_entries')->insert([
        'entry_id' => 205,
        'source_event_id' => 'preserve-existing-205',
        'title' => 'Existing projection',
        'published_at' => '2026-10-01 10:00:00',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    bindPublishedLearningEntryFeed([
        new PublishedLearningEntryData(205, 'Feed must not replace this', new DateTimeImmutable('2026-10-02T14:00:00+04:00')),
        new PublishedLearningEntryData(206, 'Missing projection', new DateTimeImmutable('2026-10-02T14:05:00+04:00')),
    ]);

    app(RebuildLearningInsightsService::class)->rebuild();

    expect(DB::table('r1_learning_insight_entries')->where('entry_id', 205)->count())->toBe(1)
        ->and(DB::table('r1_learning_insight_entries')->where('entry_id', 205)->value('title'))->toBe('Existing projection')
        ->and(DB::table('r1_learning_insight_entries')->where('entry_id', 205)->value('source_event_id'))->toBe('preserve-existing-205')
        ->and(DB::table('r1_learning_insight_entries')->where('entry_id', 206)->count())->toBe(1);
});
