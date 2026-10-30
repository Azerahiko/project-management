<?php

namespace App\Enums;

enum TaskPriority: string
{
    case LOW = 'low';
    case MEDIUM = 'medium';
    case HIGH = 'high';
    case URGENT = 'urgent';

    public function label(): string
    {
        return match ($this) {
            self::LOW => 'Rendah',
            self::MEDIUM => 'Sedang',
            self::HIGH => 'Tinggi',
            self::URGENT => 'Mendesak',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::LOW => 'bg-surface-100 text-surface-700 dark:bg-surface-800 dark:text-surface-300',
            self::MEDIUM => 'bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-300',
            self::HIGH => 'bg-accent-100 text-accent-700 dark:bg-accent-900/30 dark:text-accent-300',
            self::URGENT => 'bg-accent-100 text-accent-700 dark:bg-accent-900/30 dark:text-accent-300',
        };
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $priority) => ['value' => $priority->value, 'label' => $priority->label()], self::cases());
    }
}
