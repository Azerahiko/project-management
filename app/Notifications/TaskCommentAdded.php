<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class TaskCommentAdded extends Notification
{
    public function __construct(
        public int $taskId,
        public string $taskTitle,
        public int $projectId,
        public string $projectName,
        public int $commentId,
        public string $commentPreview,
        public string $authorName,
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
            'task_id' => $this->taskId,
            'task_title' => $this->taskTitle,
            'project_id' => $this->projectId,
            'project_name' => $this->projectName,
            'comment_id' => $this->commentId,
            'comment_preview' => $this->commentPreview,
            'author_name' => $this->authorName,
            'action' => 'comment',
            'url' => route('tasks.show', $this->taskId),
        ];
    }
}
