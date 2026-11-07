<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class ProjectOwnershipTransferred extends Notification
{
    public function __construct(
        public int $projectId,
        public string $projectName,
        public string $previousOwnerName,
        public string $newOwnerName,
        public string $transferredByName,
        public string $role, // 'previous_owner' or 'new_owner'
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
            'project_id' => $this->projectId,
            'project_name' => $this->projectName,
            'previous_owner_name' => $this->previousOwnerName,
            'new_owner_name' => $this->newOwnerName,
            'transferred_by_name' => $this->transferredByName,
            'role' => $this->role,
            'action' => 'project_ownership_transferred',
            'url' => route('projects.show', $this->projectId, false),
        ];
    }
}
