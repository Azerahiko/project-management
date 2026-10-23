<?php

namespace App\Notifications;

use Illuminate\Notifications\Notifiable;
use Illuminate\Notifications\Notification;

class ProjectMemberAdded extends Notification
{
    public function __construct(
        public int $projectId,
        public string $projectName,
        public string $role,
        public string $addedByName,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object|Notifiable $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object|Notifiable $notifiable): array
    {
        return [
            'project_id' => $this->projectId,
            'project_name' => $this->projectName,
            'role' => $this->role,
            'added_by_name' => $this->addedByName,
            'action' => 'project_member_added',
            'url' => route('projects.show', $this->projectId),
        ];
    }
}
