<?php

/**
 * @use \Illuminate\Database\Concerns\BuildsQueries<\App\Models\Notification>
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Notification model with automatic pruning support.
 *
 * Read notifications older than the configured retention period are pruned
 * automatically when the model:prune command runs.
 *
 * @property string $id
 * @property string $type
 * @property MorphOne<Notification, Notification> $notifiable
 * @property array<string, mixed> $data
 * @property string|null $read_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Notification extends DatabaseNotification
{
    use MassPrunable;

    /**
     * The retention period in days for read notifications.
     */
    protected int $retentionDays = 60;

    /**
     * The attributes that should be cast to native types.
     */
    protected function casts(): array
    {
        return [
            'data' => 'array',
            'read_at' => 'datetime',
        ];
    }

    /**
     * Get the prunable query for the model.
     *
     * Read notifications (those with a non-null read_at timestamp) that are
     * older than the retention period are considered prunable.
     */
    public function prunable(): mixed
    {
        return $this->newQuery()
            ->whereNotNull('read_at')
            ->where('created_at', '<', now()->subDays($this->retentionDays));
    }

    /**
     * Scope a query to only include unread notifications.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeUnread($query)
    {
        // DatabaseNotification already has scopeUnread, this one filters further
        return $query->whereNull('read_at');
    }
}
