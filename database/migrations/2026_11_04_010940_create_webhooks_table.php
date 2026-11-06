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
        Schema::create('webhooks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('url', 2048);
            $table->string('secret', 64);
            $table->json('events');
            $table->boolean('is_active')->default(true);
            $table->string('last_delivery_status', 20)->nullable();
            $table->unsignedSmallInteger('last_delivery_code')->nullable();
            $table->unsignedInteger('last_delivery_latency_ms')->nullable();
            $table->text('last_delivery_error')->nullable();
            $table->timestamp('last_delivery_at')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'last_delivery_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webhooks');
    }
};
