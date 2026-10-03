<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Modules\LearningCatalog\Contracts\PublishedLearningEntryFeed;
use Modules\LearningCatalog\Data\PublishedLearningEntryData;
use Modules\LearningCatalog\Data\PublishLearningEntryData;
use Modules\LearningCatalog\Events\LearningEntryPublished;
use Modules\LearningCatalog\Repositories\Contracts\LearningEntryRepositoryInterface;
use Modules\LearningCatalog\Services\LearningEntryService;

function publishLearningEntry(string $title = 'Module Boundary'): mixed
{
    return app(LearningEntryService::class)->publish(new PublishLearningEntryData($title));
}

test('publishing creates one Catalog learning entry', function (): void {
    publishLearningEntry('Public API');

    expect(DB::table('r1_learning_entries')->where('title', 'Public API')->count())->toBe(1);
});

test('publishing dispatches its event only after a successful Catalog write', function (): void {
    $entriesVisibleToTheListener = [];

    Event::listen(LearningEntryPublished::class, function (LearningEntryPublished $event) use (&$entriesVisibleToTheListener): void {
        $entriesVisibleToTheListener[] = DB::table('r1_learning_entries')
            ->where('id', $event->entryId)
            ->exists();
    });

    publishLearningEntry('After commit');

    expect($entriesVisibleToTheListener)->toBe([true]);
});

test('a failed Catalog write does not dispatch LearningEntryPublished', function (): void {
    Event::fake();
    $entries = Mockery::mock(LearningEntryRepositoryInterface::class);
    $entries->shouldReceive('createPublished')
        ->once()
        ->andThrow(new RuntimeException('Catalog write failed.'));
    app()->instance(LearningEntryRepositoryInterface::class, $entries);

    expect(fn (): mixed => publishLearningEntry('Failed write'))->toThrow(RuntimeException::class);

    Event::assertNotDispatched(LearningEntryPublished::class);
});

test('LearningEntryPublished has only the typed public event payload', function (): void {
    Event::fake();

    publishLearningEntry('Typed event');

    Event::assertDispatched(function (LearningEntryPublished $event): bool {
        expect(array_keys(get_object_vars($event)))->toBe([
            'eventId',
            'entryId',
            'title',
            'publishedAt',
        ])
            ->and($event->eventId)->toBeString()
            ->and($event->entryId)->toBeInt()
            ->and($event->title)->toBeString()
            ->and($event->publishedAt)->toBeInstanceOf(DateTimeImmutable::class);

        return true;
    });
});

test('the public feed returns readonly published-entry DTOs instead of Eloquent models', function (): void {
    publishLearningEntry('Feed entry');

    $entries = app(PublishedLearningEntryFeed::class)->all();

    expect((new ReflectionClass(PublishedLearningEntryData::class))->isReadOnly())->toBeTrue()
        ->and($entries)->not->toBeEmpty();

    foreach ($entries as $entry) {
        expect($entry)->toBeInstanceOf(PublishedLearningEntryData::class)
            ->and($entry)->not->toBeInstanceOf(Model::class);
    }
});

test('Catalog publishing succeeds when LearningEntryPublished has no registered listener', function (): void {
    Event::forget(LearningEntryPublished::class);

    publishLearningEntry('No listener required');

    expect(DB::table('r1_learning_entries')->where('title', 'No listener required')->count())->toBe(1);
});
