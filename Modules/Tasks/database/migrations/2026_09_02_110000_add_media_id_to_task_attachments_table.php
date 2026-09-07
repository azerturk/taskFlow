<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Tasks\Support\TaskAttachmentMediaBackfill;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_attachments', function (Blueprint $table): void {
            $table->foreignId('media_id')->nullable()->after('uploaded_by')->constrained('media')->restrictOnDelete();
            $table->unique('media_id');
        });

        TaskAttachmentMediaBackfill::run();
    }

    public function down(): void
    {
        Schema::table('task_attachments', function (Blueprint $table): void {
            $table->dropForeign(['media_id']);
        });

        Schema::table('task_attachments', function (Blueprint $table): void {
            $table->dropUnique('task_attachments_media_id_unique');
        });

        Schema::table('task_attachments', function (Blueprint $table): void {
            $table->dropColumn('media_id');
        });
    }
};
