<?php

namespace App\Services;

use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class TaskActivityLogger
{
    /**
     * Log an activity after the current transaction commits.
     * If no transaction is active, logs immediately.
     */
    public function log(Task $task, string $type, ?string $field, mixed $oldValue, mixed $newValue, ?User $user = null): void
    {
        if ($oldValue === $newValue) {
            return;
        }

        $description = $this->buildDescription($type, $field, $oldValue, $newValue, $user);

        $callback = function () use ($task, $type, $field, $oldValue, $newValue, $user, $description): void {
            TaskActivity::create([
                'task_id' => $task->id,
                'user_id' => $user?->id,
                'activity_type' => $type,
                'field' => $field,
                'old_value' => is_scalar($oldValue) ? (string) $oldValue : null,
                'new_value' => is_scalar($newValue) ? (string) $newValue : null,
                'description' => $description,
            ]);
        };

        // Use afterCommit so activity is only logged if the main transaction commits successfully.
        // If no transaction is active, DB::afterCommit still executes the callback.
        DB::afterCommit($callback);
    }

    /**
     * Log multiple activities after commit.
     *
     * @param  array<int, array{0: string, 1: string|null, 2: mixed, 3: mixed}>  $activities
     */
    public function logMultiple(Task $task, array $activities, ?User $user = null): void
    {
        $callbacks = [];

        foreach ($activities as [$type, $field, $oldValue, $newValue]) {
            if ($oldValue === $newValue) {
                continue;
            }

            $description = $this->buildDescription($type, $field, $oldValue, $newValue, $user);

            $callbacks[] = function () use ($task, $type, $field, $oldValue, $newValue, $user, $description): void {
                TaskActivity::create([
                    'task_id' => $task->id,
                    'user_id' => $user?->id,
                    'activity_type' => $type,
                    'field' => $field,
                    'old_value' => is_scalar($oldValue) ? (string) $oldValue : null,
                    'new_value' => is_scalar($newValue) ? (string) $newValue : null,
                    'description' => $description,
                ]);
            };
        }

        if ($callbacks === []) {
            return;
        }

        DB::afterCommit(function () use ($callbacks): void {
            foreach ($callbacks as $callback) {
                $callback();
            }
        });
    }

    /**
     * Build a human-readable description for the activity.
     */
    private function buildDescription(string $type, ?string $field, mixed $oldValue, mixed $newValue, ?User $user): string
    {
        $userName = $user->name ?? 'Sistem';

        $descriptions = [
            'task.created' => "{$userName} membuat tugas baru.",
            'task.updated' => "{$userName} memperbarui {$field} tugas.",
            'task.deleted' => "{$userName} menghapus tugas.",
            'comment.created' => "{$userName} menambahkan komentar.",
            'comment.updated' => "{$userName} mengedit komentar.",
            'comment.deleted' => "{$userName} menghapus komentar.",
        ];

        $base = $descriptions[$type] ?? "{$userName} melakukan aktivitas {$type}.";

        if ($field && ($oldValue !== null || $newValue !== null)) {
            $oldStr = $oldValue ?? 'kosong';
            $newStr = $newValue ?? 'kosong';

            return "{$base} ({$field}: {$oldStr} \u2192 {$newStr})";
        }

        return $base;
    }
}
