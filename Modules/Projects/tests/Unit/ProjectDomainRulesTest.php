<?php

use Modules\Projects\Data\AllocatedIssueNumberData;
use Modules\Projects\Enums\ProjectStatus;

test('project lifecycle transition table is fixed', function (ProjectStatus $from, array $targets): void {
    expect(array_map(fn (ProjectStatus $status): string => $status->value, $from->allowedTransitions()))
        ->toBe($targets);

    foreach (ProjectStatus::cases() as $candidate) {
        expect($from->canTransitionTo($candidate))->toBe(in_array($candidate->value, $targets, true));
    }
})->with([
    'draft' => [ProjectStatus::Draft, ['active', 'archived']],
    'active' => [ProjectStatus::Active, ['completed', 'archived']],
    'completed' => [ProjectStatus::Completed, ['active', 'archived']],
    'archived' => [ProjectStatus::Archived, []],
]);

test('project-local issue numbers produce the canonical display key', function (): void {
    $allocated = AllocatedIssueNumberData::forProject('PAY', 42);

    expect($allocated->issueNumber)->toBe(42)
        ->and($allocated->displayKey)->toBe('PAY-42');
});
