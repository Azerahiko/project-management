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
        Schema::create('webhook_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('webhook_id')->constrained()->cascadeOnDelete();
            $table->string('event');
            $table->string('payload_id')->unique(); // UUID for idempotency
            $table->json('payload');
            $table->unsignedSmallInteger('attempt')->default(1);
            $table->unsignedSmallInteger('max_attempts')->default(3);
            $table->string('status')->nullable(); // pending, success, failed, permanently_failed
            $table->unsignedSmallInteger('response_code')->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->text('response_body')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('next_retry_at')->nullable();
            $table->timestamps();

            $table->index(['webhook_id', 'status']);
            $table->index(['status', 'next_retry_at']);
            $table->index('payload_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webhook_deliveries');
    }
};
