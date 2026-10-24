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
     * Send a notification with atomic idempotency.
     * Uses INSERT ... ON CONFLICT for PostgreSQL, fallback for SQLite.
     * Returns true if inserted, false if duplicate (conflict).
     *
     * @param  array<string, mixed>  $data
     */
    public function send(User $recipient, string $type, array $data): bool
    {
        // Build payload explicitly
        $payload = array_merge($data, [
            'url' => $this->buildUrl($type, $data),
        ]);

        $notificationId = Str::uuid()->toString();
        $now = now();

        // Use PostgreSQL-specific ON CONFLICT if on PostgreSQL
        if (DB::getDriverName() === 'pgsql') {
            return $this->sendPostgreSQL($recipient, $type, $payload, $notificationId, $now);
        }

        // Fallback for SQLite (testing) - check then insert
        return $this->sendFallback($recipient, $type, $payload, $notificationId, $now);
    }

    /**
     * PostgreSQL implementation with atomic ON CONFLICT.
     *
     * @param  array<string, mixed>  $payload
     */
    private function sendPostgreSQL(User $recipient, string $type, array $payload, string $notificationId, CarbonInterface $now): bool
    {
        $conflict = $this->getConflictTarget($type, $payload);

        $inserted = DB::insert(
            "INSERT INTO notifications (id, type, notifiable_type, notifiable_id, data, read_at, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?::jsonb, NULL, ?, ?)
             {$conflict}",
            [
                $notificationId,
                $type,
                User::class,
                $recipient->id,
                json_encode($payload),
                $now,
                $now,
            ]
        );

        return $inserted > 0;
    }

    /**
     * Fallback implementation for SQLite (testing).
     * Uses raw SQL with json_extract for JSON field queries.
     *
     * @param  array<string, mixed>  $payload
     */
    private function sendFallback(User $recipient, string $type, array $payload, string $notificationId, CarbonInterface $now): bool
    {
        // Add notifiable_id to payload for where clause
        $payload['notifiable_id'] = $recipient->id;

        // Build WHERE clause for uniqueness check
        $where = $this->getFallbackWhere($type, $payload);

        // Build raw SQL query for existence check
        $conditions = [];
        $bindings = [];

        foreach ($where as $column => $value) {
            if (str_starts_with($column, 'json_extract:')) {
                $jsonPath = substr($column, 13); // 'json_extract:' is 13 characters
                $isIntegerField = in_array($jsonPath, ['task_id', 'comment_id', 'project_id'], true);
                $jsonPathBinding = "$.{$jsonPath}";
                if ($isIntegerField) {
                    $conditions[] = 'CAST(json_extract(data, ?) AS INTEGER) = ?';
                    $bindings[] = $jsonPathBinding;
                    $bindings[] = (int) $value;
                } else {
                    $conditions[] = 'json_extract(data, ?) = ?';
                    $bindings[] = $jsonPathBinding;
                    $bindings[] = (string) $value;
                }
            } else {
                $conditions[] = "{$column} = ?";
                $bindings[] = $value;
            }
        }

        $sql = 'SELECT 1 FROM notifications WHERE '.implode(' AND ', $conditions).' LIMIT 1';

        $exists = DB::select($sql, $bindings) !== [];

        if ($exists) {
            return false;
        }

        // Insert new
        $inserted = DB::table('notifications')->insert([
            'id' => $notificationId,
            'type' => $type,
            'notifiable_type' => User::class,
            'notifiable_id' => $recipient->id,
            'data' => json_encode($payload),
            'read_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $inserted > 0;
    }

    /**
     * Get WHERE conditions for fallback uniqueness check.
     * Uses json_extract: prefix for JSON fields to work with SQLite.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function getFallbackWhere(string $type, array $payload): array
    {
        $where = [
            'notifiable_id' => $payload['notifiable_id'] ?? null,
            'type' => $type,
        ];

        return match ($type) {
            TaskAssigned::class => array_merge($where, [
                'json_extract:task_id' => $payload['task_id'],
                'json_extract:event_id' => $payload['event_id'],
                'json_extract:action' => 'assigned',
            ]),
            TaskCommentAdded::class => array_merge($where, [
                'json_extract:comment_id' => $payload['comment_id'],
                'json_extract:action' => 'comment',
            ]),
            TaskStatusChanged::class => array_merge($where, [
                'json_extract:task_id' => $payload['task_id'],
                'json_extract:old_status' => $payload['old_status'],
                'json_extract:new_status' => $payload['new_status'],
                'json_extract:action' => 'status_changed',
            ]),
            ProjectMemberAdded::class => array_merge($where, [
                'json_extract:project_id' => $payload['project_id'],
                'json_extract:role' => $payload['role'],
                'json_extract:action' => 'project_member_added',
            ]),
            default => $where,
        };
    }

    /**
     * Conflict target for ON CONFLICT — uses index expressions directly.
     * Works with partial unique indexes.
     *
     * @param  array<string, mixed>  $payload
     */
    private function getConflictTarget(string $type, array $payload): string
    {
        return match ($type) {
            TaskAssigned::class => "ON CONFLICT (notifiable_id, type, (data->>'task_id'), (data->>'event_id'), (data->>'action')) WHERE type = '{$type}' AND (data->>'action') = 'assigned' DO NOTHING",

            TaskCommentAdded::class => "ON CONFLICT (notifiable_id, type, (data->>'comment_id'), (data->>'action')) WHERE type = '{$type}' AND (data->>'action') = 'comment' DO NOTHING",

            TaskStatusChanged::class => "ON CONFLICT (notifiable_id, type, (data->>'task_id'), (data->>'old_status'), (data->>'new_status'), (data->>'action')) WHERE type = '{$type}' AND (data->>'action') = 'status_changed' DO NOTHING",

            ProjectMemberAdded::class => "ON CONFLICT (notifiable_id, type, (data->>'project_id'), (data->>'role'), (data->>'action')) WHERE type = '{$type}' AND (data->>'action') = 'project_member_added' DO NOTHING",

            default => 'DO NOTHING',
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
            TaskStatusChanged::class => route('tasks.show', $data['task_id']),
            ProjectMemberAdded::class => route('projects.show', $data['project_id']),
            default => '#',
        };
    }
}
