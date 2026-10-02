<?php

namespace Modules\Dashboard\Data;

use Modules\Tasks\Enums\TaskStatus;

final readonly class DashboardSummaryData
{
    /**
     * @param  array<string, int>  $projectStatusDistribution
     * @param  array<string, int>  $taskStatusDistribution
     * @param  array<string, int>  $taskTypeDistribution
     */
    public function __construct(
        public int $activeProjects,
        public int $completedProjects,
        public int $archivedProjects,
        public int $totalTasks,
        public int $overdue,
        public int $completedToday,
        public array $projectStatusDistribution,
        public array $taskStatusDistribution,
        public array $taskTypeDistribution,
    ) {}

    public function taskStatusCount(TaskStatus $status): int
    {
        return $this->taskStatusDistribution[$status->value];
    }

    /** @return array<string, mixed> */
    public function toViewData(): array
    {
        return [
            'activeProjects' => $this->activeProjects,
            'completedProjects' => $this->completedProjects,
            'archivedProjects' => $this->archivedProjects,
            'totalTasks' => $this->totalTasks,
            'overdue' => $this->overdue,
            'completedToday' => $this->completedToday,
            'projectStatusDistribution' => $this->projectStatusDistribution,
            'taskStatusDistribution' => $this->taskStatusDistribution,
            'taskTypeDistribution' => $this->taskTypeDistribution,
        ];
    }
}
