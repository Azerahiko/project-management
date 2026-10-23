<?php

namespace App\Notifications;

use Illuminate\Notifications\Notifiable;
use Illuminate\Notifications\Notification;

class TaskAssigned extends Notification
{
    public function __construct(
        public int $taskId,
        public string $taskTitle,
        public int $projectId,
        public string $projectName,
        public string $eventId,
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
            'action' => 'assigned',
            'event_id' => $this->eventId,
            'url' => route('tasks.show', $this->taskId),
        ];
    }
}
