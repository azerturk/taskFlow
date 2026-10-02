<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->string('key', 10)->nullable();
            $table->unsignedBigInteger('next_issue_number')->default(1);
        });

        $used = [];
        foreach (DB::table('projects')->orderBy('id')->get(['id', 'name']) as $project) {
            $base = preg_replace('/[^A-Z0-9]/', '', strtoupper((string) $project->name)) ?: 'PRJ';
            $base = substr($base, 0, 10);
            if (strlen($base) < 2) {
                $base = 'PRJ';
            }

            $key = $base;
            $suffix = 2;
            while (isset($used[$key]) || DB::table('projects')->where('key', $key)->where('id', '!=', $project->id)->exists()) {
                $suffixString = (string) $suffix++;
                $key = substr($base, 0, 10 - strlen($suffixString)).$suffixString;
            }
            $used[$key] = true;

            DB::table('projects')->where('id', $project->id)->update(['key' => $key, 'next_issue_number' => 1]);
        }

        Schema::table('projects', function (Blueprint $table): void {
            $table->string('key', 10)->nullable(false)->change();
        });

        Schema::table('projects', function (Blueprint $table): void {
            $table->unique('key');
            $table->index(['key', 'next_issue_number']);
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->dropUnique(['key']);
            $table->dropIndex(['key', 'next_issue_number']);
            $table->dropColumn(['key', 'next_issue_number']);
        });
    }
};
