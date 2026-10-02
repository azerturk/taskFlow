<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_members', function (Blueprint $table): void {
            $table->string('member_role')->default('member')->after('user_id');
            $table->timestamp('joined_at')->nullable()->after('member_role');
        });
    }

    public function down(): void
    {
        Schema::table('project_members', function (Blueprint $table): void {
            $table->dropColumn(['joined_at', 'member_role']);
        });
    }
};
