<?php

namespace Modules\Tasks\Support;

use Illuminate\Support\Facades\DB;

class TaskDisplayNumberBackfill
{
    public static function run(): void
    {
        DB::table('projects')->orderBy('id')->each(function (object $project): void {
            $used = [];
            $next = 1;
            $tasks = DB::table('tasks')->where('project_id', $project->id)->orderBy('id')->get();

            foreach ($tasks as $task) {
                $candidate = self::existingIssueNumber($task->number, $project->key);
                $issueNumber = $candidate !== null && ! isset($used[$candidate]) ? $candidate : $next;

                while (isset($used[$issueNumber])) {
                    $issueNumber++;
                }

                $used[$issueNumber] = true;
                $next = max($next, $issueNumber + 1);
                $displayKey = $project->key.'-'.$issueNumber;

                if ($task->number !== $displayKey) {
                    DB::table('task_number_migration_reports')->updateOrInsert(
                        ['task_id' => $task->id],
                        ['old_number' => $task->number, 'new_display_key' => $displayKey, 'created_at' => now(), 'updated_at' => now()],
                    );
                }

                DB::table('tasks')->where('id', $task->id)->update([
                    'number' => $displayKey,
                    'issue_number' => $issueNumber,
                ]);
            }

            DB::table('projects')->where('id', $project->id)->update([
                'next_issue_number' => max((int) $project->next_issue_number, $next),
            ]);
        });
    }

    private static function existingIssueNumber(?string $number, string $projectKey): ?int
    {
        if ($number === null) {
            return null;
        }

        if (preg_match('/^'.preg_quote($projectKey, '/').'-([1-9][0-9]*)$/', $number, $matches) !== 1) {
            return null;
        }

        return (int) $matches[1];
    }
}
