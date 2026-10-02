<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Modules\Tasks\Support\TaskAttachmentMediaBackfill;

return new class extends Migration
{
    public function up(): void
    {
        Log::notice('Task attachment Media ownership migration preflight.', TaskAttachmentMediaBackfill::preflight());

        TaskAttachmentMediaBackfill::run();
        TaskAttachmentMediaBackfill::assertAssociationIntegrity();

        Schema::table('task_attachments', function (Blueprint $table): void {
            $table->foreignId('media_id')->nullable(false)->change();
        });

        Schema::table('task_attachments', function (Blueprint $table): void {
            $table->dropUnique('task_attachments_path_unique');
            $table->dropForeign(['uploaded_by']);
            $table->dropIndex(['uploaded_by']);
            $table->dropColumn(['uploaded_by', 'disk', 'path', 'original_name', 'mime_type', 'size']);
        });

        Log::notice('Task attachment Media ownership migration completed.', TaskAttachmentMediaBackfill::preflight());
    }

    public function down(): void
    {
        Schema::table('task_attachments', function (Blueprint $table): void {
            $table->foreignId('uploaded_by')->nullable()->after('task_id');
            $table->string('disk')->nullable();
            $table->string('path')->nullable();
            $table->string('original_name')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->nullable();
        });

        DB::table('task_attachments')->orderBy('id')->each(function (object $attachment): void {
            $media = DB::table('media')->where('id', $attachment->media_id)->first();
            if ($media === null) {
                throw new RuntimeException('A Media record required for task attachment rollback is missing.');
            }

            DB::table('task_attachments')->where('id', $attachment->id)->update([
                'uploaded_by' => $media->uploaded_by,
                'disk' => $media->disk,
                'path' => $media->path,
                'original_name' => $media->original_name,
                'mime_type' => $media->mime_type,
                'size' => $media->size,
            ]);
        });

        Schema::table('task_attachments', function (Blueprint $table): void {
            $table->foreignId('uploaded_by')->nullable(false)->change();
            $table->string('disk')->nullable(false)->change();
            $table->string('path')->nullable(false)->change();
            $table->string('original_name')->nullable(false)->change();
            $table->string('mime_type')->nullable(false)->change();
            $table->unsignedBigInteger('size')->nullable(false)->change();
            $table->foreign('uploaded_by')->references('id')->on('users')->restrictOnDelete();
            $table->index('uploaded_by');
            $table->unique('path');
            $table->foreignId('media_id')->nullable()->change();
        });
    }
};
