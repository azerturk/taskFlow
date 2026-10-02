<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Modules\Tasks\Support\TaskRankBackfill;

return new class extends Migration
{
    public function up(): void
    {
        $preflight = TaskRankBackfill::preflight();
        Log::notice('Task rank preservation migration preflight.', $preflight);
        TaskRankBackfill::run();

        Schema::table('tasks', function (Blueprint $table): void {
            $table->dropIndex(['project_id', 'status', 'rank']);
            $table->unique(['project_id', 'status', 'rank'], 'tasks_project_status_rank_unique');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table): void {
            $table->dropUnique('tasks_project_status_rank_unique');
            $table->index(['project_id', 'status', 'rank']);
        });
    }
};
