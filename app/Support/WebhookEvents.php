<?php

namespace App\Support;

class WebhookEvents
{
    public const TASK_CREATED = 'task.created';

    public const TASK_STATUS_CHANGED = 'task.status_changed';

    public const COMMENT_ADDED = 'comment.added';

    public const PROJECT_CREATED = 'project.created';

    public const PROJECT_COMPLETED = 'project.completed';

    public const PROJECT_OWNERSHIP_TRANSFERRED = 'project.ownership_transferred';

    /**
     * All supported outgoing webhook events.
     *
     * @return array<string, string>
     */
    public static function all(): array
    {
        return [
            self::TASK_CREATED => 'Tugas baru dibuat',
            self::TASK_STATUS_CHANGED => 'Status tugas berubah',
            self::COMMENT_ADDED => 'Komentar baru pada tugas',
            self::PROJECT_CREATED => 'Proyek baru dibuat',
            self::PROJECT_COMPLETED => 'Proyek ditandai selesai',
            self::PROJECT_OWNERSHIP_TRANSFERRED => 'Pemilik proyek berubah',
        ];
    }

    /**
     * All supported event slugs.
     *
     * @return list<string>
     */
    public static function slugs(): array
    {
        return array_keys(self::all());
    }
}
