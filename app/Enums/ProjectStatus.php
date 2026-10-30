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
            self::ACTIVE => 'bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-400',
            self::ON_HOLD => 'bg-accent-100 text-accent-700 dark:bg-accent-900/30 dark:text-accent-400',
            self::COMPLETED => 'bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-400',
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
