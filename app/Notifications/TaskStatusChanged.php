<?php

namespace App\Notifications;

use Illuminate\Notifications\Notifiable;
use Illuminate\Notifications\Notification;

class TaskStatusChanged extends Notification
{
    public function __construct(
        public int $taskId,
        public string $taskTitle,
        public int $projectId,
        public string $projectName,
        public string $oldStatus,
        public string $newStatus,
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
            'task_id' => $this->taskId,
            'task_title' => $this->taskTitle,
            'project_id' => $this->projectId,
            'project_name' => $this->projectName,
            'old_status' => $this->oldStatus,
            'new_status' => $this->newStatus,
            'action' => 'status_changed',
            'url' => route('tasks.show', $this->taskId),
        ];
    }
}
