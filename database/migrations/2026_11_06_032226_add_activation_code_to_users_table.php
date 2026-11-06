<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('activation_code_hash')->nullable()->after('password');
            $table->timestamp('activation_code_expires_at')->nullable()->after('activation_code_hash');
            $table->unsignedInteger('activation_code_attempts')->default(0)->after('activation_code_expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['activation_code_hash', 'activation_code_expires_at', 'activation_code_attempts']);
        });
    }
};
