<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table): void {
            $table->index(['project_id', 'assignee_id']);
            $table->index(['project_id', 'creator_id']);
            $table->index('due_at');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table): void {
            $table->dropIndex(['project_id', 'assignee_id']);
            $table->dropIndex(['project_id', 'creator_id']);
            $table->dropIndex(['due_at']);
        });
    }
};
