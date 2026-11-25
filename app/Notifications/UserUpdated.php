<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class UserUpdated extends Notification
{
    public function __construct(
        public string $changedFields,
        public string $updatedByName,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'changed_fields' => $this->changedFields,
            'updated_by_name' => $this->updatedByName,
            'action' => 'user_updated',
            'url' => '/settings',
        ];
    }
}
