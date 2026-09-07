<?php

use Carbon\CarbonImmutable;
use Modules\Projects\Enums\ProjectStatus;
use Modules\Tasks\Enums\TaskLabelColor;
use Modules\Tasks\Enums\TaskPriority;
use Modules\Tasks\Enums\TaskStatus;
use Modules\Tasks\Enums\TaskType;
use Modules\Tasks\Exceptions\InvalidTaskLabel;
use Modules\Tasks\Support\TaskLabelName;
use Modules\Tasks\Support\TaskRankSequence;
use Modules\Tasks\Support\TaskStatusTimestamps;
use Modules\Tasks\Support\TaskTransitionRules;

test('work-item workflow transition table is fixed', function (TaskStatus $from, array $targets): void {
    expect(array_map(fn (TaskStatus $status): string => $status->value, $from->allowedTransitions()))
        ->toBe($targets);
})->with([
    'backlog' => [TaskStatus::Backlog, ['todo', 'cancelled']],
    'todo' => [TaskStatus::Todo, ['backlog', 'in_progress', 'cancelled']],
    'in progress' => [TaskStatus::InProgress, ['todo', 'review', 'cancelled']],
    'review' => [TaskStatus::Review, ['in_progress', 'done', 'cancelled']],
    'done' => [TaskStatus::Done, ['in_progress']],
    'cancelled' => [TaskStatus::Cancelled, ['backlog']],
]);

test('manager and assignee transition authority is deterministic', function (): void {
    expect(TaskTransitionRules::available(TaskStatus::Todo, ProjectStatus::Active, true, true, true, true, false))
        ->toBe(TaskStatus::Todo->allowedTransitions())
        ->and(TaskTransitionRules::available(TaskStatus::Todo, ProjectStatus::Active, true, true, false, true, true))
        ->toBe(TaskStatus::Todo->allowedTransitions())
        ->and(TaskTransitionRules::available(TaskStatus::Done, ProjectStatus::Active, true, true, false, true, true))
        ->toBe([])
        ->and(TaskTransitionRules::available(TaskStatus::Done, ProjectStatus::Active, true, true, true, true, false))
        ->toBe([TaskStatus::InProgress])
        ->and(TaskTransitionRules::available(TaskStatus::Todo, ProjectStatus::Completed, true, true, true, true, false))
        ->toBe([])
        ->and(TaskTransitionRules::available(TaskStatus::Todo, ProjectStatus::Active, false, true, true, true, false))
        ->toBe([]);
});

test('status timestamps preserve first start and clear completion on reopen', function (): void {
    $first = CarbonImmutable::parse('2026-09-07T10:00:00Z');
    $later = $first->addHour();
    $started = TaskStatusTimestamps::resolve(TaskStatus::Todo, TaskStatus::InProgress, null, null, $first);
    $preserved = TaskStatusTimestamps::resolve(TaskStatus::InProgress, TaskStatus::InProgress, $started['started_at'], null, $later);
    expect($preserved['started_at']?->format(DATE_ATOM))->toBe($first->format(DATE_ATOM));

    $completed = TaskStatusTimestamps::resolve(TaskStatus::Review, TaskStatus::Done, $preserved['started_at'], null, $later);
    expect($completed['completed_at']?->format(DATE_ATOM))->toBe($later->format(DATE_ATOM));

    $reopened = TaskStatusTimestamps::resolve(TaskStatus::Done, TaskStatus::InProgress, $completed['started_at'], $completed['completed_at'], $later->addHour());
    expect($reopened['completed_at'])->toBeNull()
        ->and($reopened['started_at']?->format(DATE_ATOM))->toBe($first->format(DATE_ATOM));
});

test('priorities have one fixed low-to-urgent order', function (): void {
    expect(array_map(fn (TaskPriority $priority): string => $priority->value, TaskPriority::cases()))
        ->toBe(['low', 'medium', 'high', 'urgent'])
        ->and(array_map(fn (TaskPriority $priority): int => $priority->weight(), TaskPriority::cases()))
        ->toBe([1, 2, 3, 4]);
});

test('parent rules permit exactly one subtask level', function (): void {
    expect(TaskType::Subtask->requiresParent())->toBeTrue()
        ->and(TaskType::Subtask->canBeParent())->toBeFalse()
        ->and(TaskType::Task->requiresParent())->toBeFalse()
        ->and(TaskType::Bug->canBeParent())->toBeTrue()
        ->and(TaskType::Story->canBeParent())->toBeTrue();
});

test('rank allocation and rebalancing are deterministic and skip reserved soft-delete ranks', function (): void {
    expect(TaskRankSequence::next([]))->toBe(1000)
        ->and(TaskRankSequence::next([3000, 1000, 2000]))->toBe(4000)
        ->and(TaskRankSequence::rebalance([7, 3, 9]))->toBe([7 => 1000, 3 => 2000, 9 => 3000])
        ->and(TaskRankSequence::rebalance([7, 3], [1000, 3000]))->toBe([7 => 2000, 3 => 4000]);
});

test('label names normalize consistently and colors remain fixed', function (): void {
    $label = TaskLabelName::from('  Release Ready  ');

    expect($label->name)->toBe('Release Ready')
        ->and($label->slug)->toBe('release-ready')
        ->and(TaskLabelColor::cases())->toHaveCount(10)
        ->and(array_unique(array_map(fn (TaskLabelColor $color): string => $color->value, TaskLabelColor::cases())))
        ->toHaveCount(10)
        ->and(fn () => TaskLabelName::from(' --- '))->toThrow(InvalidTaskLabel::class);
});
