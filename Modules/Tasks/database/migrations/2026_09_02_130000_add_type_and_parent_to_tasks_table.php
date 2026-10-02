<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table): void {
            $table->foreignId('parent_id')->nullable()->after('project_id')->constrained('tasks')->restrictOnDelete();
            $table->string('type')->default('task')->after('parent_id');
            $table->index(['project_id', 'parent_id']);
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table): void {
            $table->dropIndex(['project_id', 'parent_id']);
            $table->dropConstrainedForeignId('parent_id');
            $table->dropColumn('type');
        });
    }
};
