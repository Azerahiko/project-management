<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string $url
 * @property string $secret
 * @property list<string> $events
 * @property bool $is_active
 * @property string|null $last_delivery_status
 * @property int|null $last_delivery_code
 * @property int|null $last_delivery_latency_ms
 * @property string|null $last_delivery_error
 * @property Carbon|null $last_delivery_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 */
#[Fillable([
    'user_id',
    'name',
    'url',
    'secret',
    'events',
    'is_active',
    'last_delivery_status',
    'last_delivery_code',
    'last_delivery_latency_ms',
    'last_delivery_error',
    'last_delivery_at',
])]
class Webhook extends Model
{
    /**
     * Never serialize the signing secret.
     *
     * @var list<string>
     */
    protected $hidden = ['secret'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'events' => 'array',
            'is_active' => 'boolean',
            'last_delivery_code' => 'integer',
            'last_delivery_latency_ms' => 'integer',
            'last_delivery_at' => 'datetime',
        ];
    }

    /**
     * Get the user who registered the webhook.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope the query to active webhooks only.
     *
     * @param  Builder<Webhook>  $query
     * @return Builder<Webhook>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Determine whether this webhook listens to the given event.
     */
    public function listensTo(string $event): bool
    {
        return in_array($event, $this->events ?? [], true);
    }
}
