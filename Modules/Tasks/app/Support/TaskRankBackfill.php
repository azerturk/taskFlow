<?php

namespace Modules\Tasks\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TaskRankBackfill
{
    /**
     * @return array{affected_columns: int, affected_tasks: int, duplicate_tasks: int, non_positive_tasks: int, columns: list<string>}
     */
    public static function preflight(): array
    {
        $affected = self::affectedColumns();

        return [
            'affected_columns' => $affected->count(),
            'affected_tasks' => $affected->sum(fn (Collection $tasks): int => $tasks->count()),
            'duplicate_tasks' => $affected->sum(function (Collection $tasks): int {
                return $tasks->groupBy('rank')->filter(fn (Collection $ranked): bool => $ranked->count() > 1)->sum->count();
            }),
            'non_positive_tasks' => $affected->sum(fn (Collection $tasks): int => $tasks->where('rank', '<=', 0)->count()),
            'columns' => $affected->keys()->values()->all(),
        ];
    }

    public static function run(): void
    {
        self::affectedColumns()->each(function (Collection $tasks): void {
            $tasks->values()->each(function (object $task, int $offset): void {
                DB::table('tasks')->where('id', $task->id)->update(['rank' => ($offset + 1) * 1000]);
            });
        });
    }

    /** @return Collection<string, Collection<int, object>> */
    private static function affectedColumns(): Collection
    {
        return DB::table('tasks')
            ->select(['id', 'project_id', 'status', 'rank'])
            ->orderBy('project_id')
            ->orderBy('status')
            ->orderBy('rank')
            ->orderBy('id')
            ->get()
            ->groupBy(fn (object $task): string => $task->project_id.'|'.$task->status)
            ->filter(function (Collection $tasks): bool {
                return $tasks->contains(fn (object $task): bool => (int) $task->rank <= 0)
                    || $tasks->pluck('rank')->duplicates()->isNotEmpty();
            });
    }
}
