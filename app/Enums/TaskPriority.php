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
            self::MEDIUM => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
            self::HIGH => 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400',
            self::URGENT => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
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
