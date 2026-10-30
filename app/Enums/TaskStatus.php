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
            self::IN_PROGRESS => 'bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-300',
            self::REVIEW => 'bg-accent-100 text-accent-700 dark:bg-accent-900/30 dark:text-accent-300',
            self::DONE => 'bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-300',
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
