<?php

namespace App\Notifications;

/**
 * Custom Notification model with automatic pruning support.
 *
 * Read notifications older than the configured retention period are pruned
 * automatically when the model:prune command runs, or when Notification::prune()
 * is called. The retention period is determined by the
 * 'notification.retention_days' configuration option (default: 60).
 */
class Notification extends \Illuminate\Notifications\Notification
{
    /**
     * The retention period in days for read notifications.
     */
    protected int $retentionDays = 60;

    /**
     * Get the retention period in days.
     */
    public function getRetentionDays(): int
    {
        return $this->retentionDays;
    }

    /**
     * Set the retention period in days.
     *
     * @return $this
     */
    public function setRetentionDays(int $days): static
    {
        $this->retentionDays = $days;

        return $this;
    }
}
