<?php

namespace Modules\Dashboard\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Dashboard\Data\DashboardSummaryData;
use Modules\Tasks\Enums\TaskStatus;

class DashboardSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var DashboardSummaryData $summary */
        $summary = $this->resource;

        return [
            'active_projects' => $summary->activeProjects,
            'completed_projects' => $summary->completedProjects,
            'archived_projects' => $summary->archivedProjects,
            'total_tasks' => $summary->totalTasks,
            'backlog' => $summary->taskStatusCount(TaskStatus::Backlog),
            'todo' => $summary->taskStatusCount(TaskStatus::Todo),
            'in_progress' => $summary->taskStatusCount(TaskStatus::InProgress),
            'review' => $summary->taskStatusCount(TaskStatus::Review),
            'done' => $summary->taskStatusCount(TaskStatus::Done),
            'cancelled' => $summary->taskStatusCount(TaskStatus::Cancelled),
            'overdue' => $summary->overdue,
            'completed_today' => $summary->completedToday,
            'project_status_distribution' => $summary->projectStatusDistribution,
            'task_status_distribution' => $summary->taskStatusDistribution,
            'task_type_distribution' => $summary->taskTypeDistribution,
        ];
    }
}
