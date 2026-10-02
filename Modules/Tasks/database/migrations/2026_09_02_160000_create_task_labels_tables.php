<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_labels', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('slug', 100);
            $table->string('color', 7);
            $table->timestamps();
            $table->unique(['project_id', 'name']);
            $table->unique(['project_id', 'slug']);
        });
        Schema::create('task_label', function (Blueprint $table): void {
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->foreignId('task_label_id')->constrained('task_labels')->cascadeOnDelete();
            $table->primary(['task_id', 'task_label_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_label');
        Schema::dropIfExists('task_labels');
    }
};
