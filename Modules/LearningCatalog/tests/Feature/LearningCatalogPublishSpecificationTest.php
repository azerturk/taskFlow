<?php

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Modules\LearningCatalog\Contracts\PublishedLearningEntryFeed;
use Modules\LearningCatalog\Data\PublishedLearningEntryData;
use Modules\LearningCatalog\Data\PublishLearningEntryData;
use Modules\LearningCatalog\Events\LearningEntryPublished;
use Modules\LearningCatalog\Exceptions\InvalidLearningEntryTitle;
use Modules\LearningCatalog\Models\LearningEntry;
use Modules\LearningCatalog\Repositories\Contracts\LearningEntryRepositoryInterface;
use Modules\LearningCatalog\Services\LearningEntryService;

function publishCatalogSpecificationEntry(string $title = 'Module Boundary'): LearningEntry
{
    return app(LearningEntryService::class)->publish(new PublishLearningEntryData($title));
}

test('publishing persists one canonical Catalog learning entry', function (): void {
    Event::fake([LearningEntryPublished::class]);
    $this->travelTo(new DateTimeImmutable('2026-10-08 12:00:00'));

    $entry = publishCatalogSpecificationEntry(" \tPublic API\n ");

    $this->assertDatabaseCount('r1_learning_entries', 1);
    $this->assertDatabaseHas('r1_learning_entries', [
        'id' => $entry->id,
        'title' => 'Public API',
        'published_at' => '2026-10-08 12:00:00',
    ]);
    expect($entry->published_at)->toBeInstanceOf(DateTimeImmutable::class);
    Event::assertDispatched(LearningEntryPublished::class, 1);
});

test('Catalog accepts trimmed one and 255 character Unicode titles', function (string $title): void {
    Event::fake([LearningEntryPublished::class]);

    $data = new PublishLearningEntryData(" \t{$title}\r\n ");
    $entry = app(LearningEntryService::class)->publish($data);

    expect($data->title)->toBe($title)
        ->and($entry->title)->toBe($title)
        ->and((new ReflectionClass($data))->isReadOnly())->toBeTrue();
    $this->assertDatabaseHas('r1_learning_entries', ['id' => $entry->id, 'title' => $title]);
    Event::assertDispatched(LearningEntryPublished::class, 1);
})->with([
    'one character' => 'Ə',
    '255 characters' => str_repeat('Ə', 255),
]);

test('Catalog rejects invalid titles before persistence or event dispatch', function (string $title): void {
    Event::fake([LearningEntryPublished::class]);

    expect(fn (): LearningEntry => publishCatalogSpecificationEntry($title))
        ->toThrow(InvalidLearningEntryTitle::class);

    $this->assertDatabaseCount('r1_learning_entries', 0);
    $this->assertDatabaseCount('r1_learning_insight_entries', 0);
    Event::assertNotDispatched(LearningEntryPublished::class);
})->with([
    'empty' => '',
    'ASCII whitespace' => " \t\r\n ",
    'Unicode whitespace' => "\u{00A0}\u{2003}",
    '256 Unicode characters' => str_repeat('Ə', 256),
]);

test('a failure after a real Catalog insert rolls it back and dispatches no event', function (): void {
    Event::fake([LearningEntryPublished::class]);
    $realEntries = app(LearningEntryRepositoryInterface::class);
    $insertedEntryId = null;
    $entries = Mockery::mock(LearningEntryRepositoryInterface::class);
    $entries->shouldReceive('createPublished')->once()
        ->andReturnUsing(function (PublishLearningEntryData $data) use ($realEntries, &$insertedEntryId): never {
            $entry = $realEntries->createPublished($data);
            $insertedEntryId = $entry->id;

            expect(DB::table('r1_learning_entries')->where('id', $insertedEntryId)->exists())->toBeTrue();

            throw new RuntimeException('Catalog failure after insert.');
        });
    app()->instance(LearningEntryRepositoryInterface::class, $entries);

    expect(fn (): LearningEntry => publishCatalogSpecificationEntry('Failed write'))
        ->toThrow(RuntimeException::class, 'Catalog failure after insert.');

    expect($insertedEntryId)->toBeInt();
    $this->assertDatabaseCount('r1_learning_entries', 0);
    $this->assertDatabaseCount('r1_learning_insight_entries', 0);
    Event::assertNotDispatched(LearningEntryPublished::class);
});

test('LearningEntryPublished exposes only its immutable typed public payload', function (): void {
    Event::fake([LearningEntryPublished::class]);

    $entry = publishCatalogSpecificationEntry('Typed event');

    Event::assertDispatched(LearningEntryPublished::class, function (LearningEntryPublished $event) use ($entry): bool {
        expect((new ReflectionClass($event))->isReadOnly())->toBeTrue()
            ->and($event)->toBeInstanceOf(ShouldDispatchAfterCommit::class)
            ->and(array_keys(get_object_vars($event)))->toBe(['eventId', 'entryId', 'title', 'publishedAt'])
            ->and(Str::isUuid($event->eventId))->toBeTrue()
            ->and($event->entryId)->toBe($entry->id)
            ->and($event->title)->toBe('Typed event')
            ->and($event->publishedAt)->toBeInstanceOf(DateTimeImmutable::class)
            ->and($event->publishedAt->format('c'))->toBe($entry->published_at->format('c'));

        expect(fn () => $event->title = 'Mutated')->toThrow(Error::class);

        return true;
    });
});

test('the real public feed returns a stable ordered list of immutable DTOs', function (): void {
    Event::fake([LearningEntryPublished::class]);
    expect(app(PublishedLearningEntryFeed::class)->all())->toBe([]);

    $this->travelTo(new DateTimeImmutable('2026-10-08 12:00:00'));
    $first = publishCatalogSpecificationEntry('Z first entry');
    $this->travelTo(new DateTimeImmutable('2026-10-07 12:00:00'));
    $second = publishCatalogSpecificationEntry('A second entry');
    $entries = app(PublishedLearningEntryFeed::class)->all();

    expect(array_is_list($entries))->toBeTrue()
        ->and(array_map(fn (PublishedLearningEntryData $entry): int => $entry->id, $entries))->toBe([$first->id, $second->id])
        ->and(array_map(fn (PublishedLearningEntryData $entry): string => $entry->title, $entries))->toBe(['Z first entry', 'A second entry']);

    foreach ($entries as $entry) {
        expect($entry)->toBeInstanceOf(PublishedLearningEntryData::class)
            ->and($entry)->not->toBeInstanceOf(Model::class)
            ->and((new ReflectionClass($entry))->isReadOnly())->toBeTrue()
            ->and(array_keys(get_object_vars($entry)))->toBe(['id', 'title', 'publishedAt'])
            ->and($entry->publishedAt)->toBeInstanceOf(DateTimeImmutable::class);
    }

    expect(fn () => $entries[0]->title = 'Mutated')->toThrow(Error::class);
    expect($entries[0]->publishedAt->format('c'))->toBe($first->published_at->format('c'));
});

test('Catalog publishing succeeds when no public-event listener is registered', function (): void {
    Event::forget(LearningEntryPublished::class);

    $entry = publishCatalogSpecificationEntry('No listener required');

    $this->assertDatabaseHas('r1_learning_entries', ['id' => $entry->id, 'title' => 'No listener required']);
    $this->assertDatabaseCount('r1_learning_insight_entries', 0);
});
