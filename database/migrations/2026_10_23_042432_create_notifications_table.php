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
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->jsonb('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['notifiable_type', 'notifiable_id', 'read_at']);
        });

        // Partial unique indexes with expressions - only for PostgreSQL
        // SQLite doesn't support WHERE clause in CREATE UNIQUE INDEX
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("
                CREATE UNIQUE INDEX notif_unique_task_assigned
                ON notifications (notifiable_id, type, (data->>'task_id'), (data->>'event_id'), (data->>'action'))
                WHERE type = 'App\\Notifications\\TaskAssigned' AND (data->>'action') = 'assigned'
            ");

            DB::statement("
                CREATE UNIQUE INDEX notif_unique_comment
                ON notifications (notifiable_id, type, (data->>'comment_id'), (data->>'action'))
                WHERE type = 'App\\Notifications\\TaskCommentAdded' AND (data->>'action') = 'comment'
            ");

            DB::statement("
                CREATE UNIQUE INDEX notif_unique_status_changed
                ON notifications (notifiable_id, type, (data->>'task_id'), (data->>'old_status'), (data->>'new_status'), (data->>'action'))
                WHERE type = 'App\\Notifications\\TaskStatusChanged' AND (data->>'action') = 'status_changed'
            ");

            DB::statement("
                CREATE UNIQUE INDEX notif_unique_project_member
                ON notifications (notifiable_id, type, (data->>'project_id'), (data->>'role'), (data->>'action'))
                WHERE type = 'App\\Notifications\\ProjectMemberAdded' AND (data->>'action') = 'project_member_added'
            ");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
