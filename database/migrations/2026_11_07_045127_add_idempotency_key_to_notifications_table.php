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
        Schema::table('notifications', function (Blueprint $table) {
            $table->string('idempotency_key')->nullable()->after('data');
        });

        // Create unique index for idempotency
        // Use non-partial index for PostgreSQL to support ON CONFLICT (idempotency_key)
        // idempotency_key is never NULL in practice (always generated)
        DB::statement('CREATE UNIQUE INDEX notifications_idempotency_key_unique ON notifications (idempotency_key)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex('notifications_idempotency_key_unique');
            $table->dropColumn('idempotency_key');
        });
    }
};
