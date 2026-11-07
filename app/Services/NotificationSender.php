<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\ProjectMemberAdded;
use App\Notifications\TaskAssigned;
use App\Notifications\TaskCommentAdded;
use App\Notifications\TaskStatusChanged;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class NotificationSender
{
    /**
     * Send a notification with atomic idempotency using an idempotency key.
     * Works for both PostgreSQL and SQLite.
     * Returns true if inserted, false if duplicate (conflict).
     *
     * @param  array<string, mixed>  $data
     */
    public function send(User $recipient, string $type, array $data): bool
    {
        // Build payload explicitly
        $payload = array_merge($data, [
            'url' => $this->buildUrl($type, $data),
            'notifiable_id' => $recipient->id,
        ]);

        $notificationId = Str::uuid()->toString();
        $now = now();
        $idempotencyKey = $this->generateIdempotencyKey($type, $payload);

        try {
            $inserted = DB::table('notifications')->insert([
                'id' => $notificationId,
                'type' => $type,
                'notifiable_type' => User::class,
                'notifiable_id' => $recipient->id,
                'data' => json_encode($payload),
                'idempotency_key' => $idempotencyKey,
                'read_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return $inserted > 0;
        } catch (QueryException $exception) {
            // Unique constraint violation (idempotency key already exists)
            // PostgreSQL: SQLSTATE 23505 (unique_violation), MySQL: 1062, SQLite: 19 (constraint failed)
            $sqlState = $exception->getCode();
            $driverCode = $exception->errorInfo[1] ?? null;
            if ($sqlState === '23505' || $sqlState === '23000' || $driverCode === 1062 || $driverCode === 19) {
                return false;
            }
            throw $exception;
        }
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
