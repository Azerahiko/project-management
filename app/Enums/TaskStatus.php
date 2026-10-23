<?php

namespace App\Enums;

enum TaskStatus: string
{
    case TODO = 'todo';
    case IN_PROGRESS = 'in_progress';
    case REVIEW = 'review';
    case DONE = 'done';

    public function label(): string
    {
        return match ($this) {
            self::TODO => 'To Do',
            self::IN_PROGRESS => 'Sedang Dikerjakan',
            self::REVIEW => 'Review',
            self::DONE => 'Selesai',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::TODO => 'bg-surface-100 text-surface-700 dark:bg-surface-800 dark:text-surface-300',
            self::IN_PROGRESS => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
            self::REVIEW => 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400',
            self::DONE => 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
        };
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $status) => ['value' => $status->value, 'label' => $status->label()], self::cases());
    }
}
