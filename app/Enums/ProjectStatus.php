<?php

namespace App\Enums;

enum ProjectStatus: string
{
    case DRAFT = 'draft';
    case ACTIVE = 'active';
    case ON_HOLD = 'on_hold';
    case COMPLETED = 'completed';
    case ARCHIVED = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::ACTIVE => 'Aktif',
            self::ON_HOLD => 'Ditahan',
            self::COMPLETED => 'Selesai',
            self::ARCHIVED => 'Arsip',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::DRAFT => 'bg-surface-100 text-surface-700 dark:bg-surface-800 dark:text-surface-300',
            self::ACTIVE => 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
            self::ON_HOLD => 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400',
            self::COMPLETED => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
            self::ARCHIVED => 'bg-surface-100 text-surface-500 dark:bg-surface-800 dark:text-surface-400',
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
