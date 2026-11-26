<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Hard-delete any rows that were soft-deleted
        DB::table('task_comments')->whereNotNull('deleted_at')->delete();

        // Drop the soft deletes column
        Schema::table('task_comments', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Re-add soft deletes column (cannot restore data, so this is a best-effort rollback)
        Schema::table('task_comments', function (Blueprint $table) {
            $table->softDeletes();
        });
    }
};
