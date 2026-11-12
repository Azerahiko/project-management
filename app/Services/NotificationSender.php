<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\ProjectMemberAdded;
use App\Notifications\TaskAssigned;
use App\Notifications\TaskCommentAdded;
use App\Notifications\TaskStatusChanged;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class NotificationSender
{
    /**
     * Map notification types to preference keys.
     *
     * @var array<string, string>
     */
    private const TYPE_TO_PREFERENCE = [
        TaskAssigned::class => 'task_updates',
        TaskStatusChanged::class => 'task_updates',
        TaskCommentAdded::class => 'comments',
        ProjectMemberAdded::class => 'mentions',
        // ProjectOwnershipTransferred uses 'mentions' as it's a significant event
    ];

    /**
     * Send a notification with atomic idempotency using an idempotency key.
     * Uses database-specific upsert/ignore to avoid transaction abort on conflict.
     * Returns true if inserted, false if duplicate (conflict).
     *
     * @param  array<string, mixed>  $data
     */
    public function send(User $recipient, string $type, array $data): bool
    {
        // Check user notification preferences before sending
        if (! $this->shouldSendNotification($recipient, $type)) {
            return false;
        }

        // Build payload explicitly
        $payload = array_merge($data, [
            'url' => $this->buildUrl($type, $data),
            'notifiable_id' => $recipient->id,
        ]);

        $notificationId = Str::uuid()->toString();
        $now = now();
        $idempotencyKey = $this->generateIdempotencyKey($type, $payload);

        $inserted = $this->insertIgnoringConflict($notificationId, $type, $recipient->id, $payload, $idempotencyKey, $now);

        return $inserted > 0;
    }

    /**
     * Check if notification should be sent based on user preferences.
     */
    private function shouldSendNotification(User $recipient, string $type): bool
    {
        $preferenceKey = self::TYPE_TO_PREFERENCE[$type] ?? null;

        // If no preference mapping exists, send by default (fail-open for unknown types)
        if ($preferenceKey === null) {
            return true;
        }

        $preferences = $recipient->notificationPreferences();

        // If preference is explicitly set to false, don't send
        return $preferences[$preferenceKey] ?? true;
    }

    /**
     * Insert notification using database-specific conflict handling.
     * - PostgreSQL: INSERT ... ON CONFLICT (idempotency_key) DO NOTHING RETURNING id
     * - SQLite: INSERT OR IGNORE ... (check changes())
     * - MySQL: INSERT IGNORE ... (check affected rows)
     *
     * @param  array<string, mixed>  $payload
     */
    private function insertIgnoringConflict(
        string $notificationId,
        string $type,
        int $notifiableId,
        array $payload,
        string $idempotencyKey,
        CarbonInterface $now
    ): int {
        $driver = DB::connection()->getDriverName();
        $table = 'notifications';
        $columns = ['id', 'type', 'notifiable_type', 'notifiable_id', 'data', 'idempotency_key', 'read_at', 'created_at', 'updated_at'];
        $values = [$notificationId, $type, User::class, $notifiableId, json_encode($payload), $idempotencyKey, null, $now, $now];

        return match ($driver) {
            'pgsql' => $this->insertPgsql($table, $columns, $values),
            'sqlite' => $this->insertSqlite($table, $columns, $values),
            default => $this->insertMysql($table, $columns, $values),
        };
    }

    /**
     * PostgreSQL: use RETURNING to detect actual insert.
     *
     * @param  list<string>  $columns
     * @param  list<mixed>  $values
     */
    private function insertPgsql(string $table, array $columns, array $values): int
    {
        $sql = "INSERT INTO {$table} (".implode(', ', $columns).') VALUES ('.str_repeat('?,', count($columns) - 1).'?) ON CONFLICT (idempotency_key) DO NOTHING RETURNING id';
        $result = DB::connection()->select($sql, $values);

        return count($result) > 0 ? 1 : 0;
    }

    /**
     * SQLite: use changes() to detect actual insert.
     *
     * @param  list<string>  $columns
     * @param  list<mixed>  $values
     */
    private function insertSqlite(string $table, array $columns, array $values): int
    {
        $sql = "INSERT OR IGNORE INTO {$table} (".implode(', ', $columns).') VALUES ('.str_repeat('?,', count($columns) - 1).'?)';
        DB::connection()->insert($sql, $values);
        // Get the number of rows actually changed
        $changes = DB::connection()->select('SELECT changes() as c')[0]->c ?? 0;

        return (int) $changes;
    }

    /**
     * MySQL: use affected rows to detect actual insert.
     *
     * @param  list<string>  $columns
     * @param  list<mixed>  $values
     */
    private function insertMysql(string $table, array $columns, array $values): int
    {
        $sql = "INSERT IGNORE INTO {$table} (".implode(', ', $columns).') VALUES ('.str_repeat('?,', count($columns) - 1).'?)';

        return (int) DB::connection()->insert($sql, $values);
    }

    /**
     * Generate a unique idempotency key based on notification type and payload.
     * This key ensures duplicate notifications are prevented at the database level.
     *
     * @param  array<string, mixed>  $payload
     */
    private function generateIdempotencyKey(string $type, array $payload): string
    {
        $notifiableId = $payload['notifiable_id'] ?? '';
        $keyParts = [$type, (string) $notifiableId];

        return match ($type) {
            TaskAssigned::class => implode('|', array_merge($keyParts, [
                $payload['task_id'] ?? '',
                $payload['event_id'] ?? '',
                $payload['action'] ?? 'assigned',
            ])),

            TaskCommentAdded::class => implode('|', array_merge($keyParts, [
                $payload['comment_id'] ?? '',
                $payload['action'] ?? 'comment',
            ])),

            TaskStatusChanged::class => implode('|', array_merge($keyParts, [
                $payload['task_id'] ?? '',
                $payload['old_status'] ?? '',
                $payload['new_status'] ?? '',
                $payload['action'] ?? 'status_changed',
            ])),

            ProjectMemberAdded::class => implode('|', array_merge($keyParts, [
                $payload['project_id'] ?? '',
                $payload['role'] ?? '',
                $payload['action'] ?? 'project_member_added',
            ])),

            default => implode('|', array_merge($keyParts, [
                $payload['action'] ?? 'unknown',
            ])),
        };
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function buildUrl(string $type, array $data): string
    {
        return match ($type) {
            TaskAssigned::class,
            TaskCommentAdded::class,
            TaskStatusChanged::class => route('tasks.show', $data['task_id'], false),
            ProjectMemberAdded::class => route('projects.show', $data['project_id'], false),
            default => '#',
        };
    }
}
