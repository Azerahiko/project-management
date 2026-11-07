<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $webhook_id
 * @property string $event
 * @property string $payload_id
 * @property array<string, mixed> $payload
 * @property int $attempt
 * @property int $max_attempts
 * @property string|null $status
 * @property int|null $response_code
 * @property int|null $latency_ms
 * @property string|null $response_body
 * @property string|null $error
 * @property Carbon|null $delivered_at
 * @property Carbon|null $next_retry_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Webhook $webhook
 */
#[Fillable([
    'webhook_id',
    'event',
    'payload_id',
    'payload',
    'attempt',
    'max_attempts',
    'status',
    'response_code',
    'latency_ms',
    'response_body',
    'error',
    'delivered_at',
    'next_retry_at',
])]
class WebhookDelivery extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'attempt' => 'integer',
            'max_attempts' => 'integer',
            'response_code' => 'integer',
            'latency_ms' => 'integer',
            'delivered_at' => 'datetime',
            'next_retry_at' => 'datetime',
        ];
    }

    /**
     * Get the webhook that this delivery belongs to.
     *
     * @return BelongsTo<Webhook, $this>
     */
    public function webhook(): BelongsTo
    {
        return $this->belongsTo(Webhook::class);
    }

    /**
     * Scope the query to pending deliveries.
     *
     * @param  Builder<WebhookDelivery>  $query
     * @return Builder<WebhookDelivery>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope the query to failed deliveries that can be retried.
     *
     * @param  Builder<WebhookDelivery>  $query
     * @return Builder<WebhookDelivery>
     */
    public function scopeRetryable(Builder $query): Builder
    {
        return $query->where('status', 'failed')
            ->where('attempt', '<', 'max_attempts')
            ->where(function ($q) {
                $q->whereNull('next_retry_at')
                    ->orWhere('next_retry_at', '<=', now());
            });
    }

    /**
     * Scope the query to permanently failed deliveries.
     *
     * @param  Builder<WebhookDelivery>  $query
     * @return Builder<WebhookDelivery>
     */
    public function scopePermanentlyFailed(Builder $query): Builder
    {
        return $query->where('status', 'permanently_failed');
    }

    /**
     * Scope the query to successful deliveries.
     *
     * @param  Builder<WebhookDelivery>  $query
     * @return Builder<WebhookDelivery>
     */
    public function scopeSuccessful(Builder $query): Builder
    {
        return $query->where('status', 'success');
    }
}
