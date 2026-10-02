<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Tasks\Support\TaskDisplayNumberBackfill;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table): void {
            $table->unsignedBigInteger('issue_number')->nullable()->after('number');
        });

        Schema::create('task_number_migration_reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('task_id')->unique()->constrained('tasks')->cascadeOnDelete();
            $table->string('old_number')->nullable();
            $table->string('new_display_key');
            $table->timestamps();
        });

        TaskDisplayNumberBackfill::run();

        Schema::table('tasks', function (Blueprint $table): void {
            $table->unsignedBigInteger('issue_number')->nullable(false)->change();
            $table->string('number')->nullable(false)->change();
            $table->unique(['project_id', 'issue_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_number_migration_reports');

        Schema::table('tasks', function (Blueprint $table): void {
            $table->dropUnique('tasks_project_id_issue_number_unique');
            $table->dropColumn('issue_number');
        });
    }
};
