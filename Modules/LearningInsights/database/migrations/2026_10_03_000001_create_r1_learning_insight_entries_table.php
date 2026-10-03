<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('r1_learning_insight_entries', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('entry_id')->unique();
            $table->uuid('source_event_id')->nullable()->unique();
            $table->string('title');
            $table->timestamp('published_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('r1_learning_insight_entries');
    }
};
