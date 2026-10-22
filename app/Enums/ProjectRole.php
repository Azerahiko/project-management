<?php

namespace App\Enums;

enum ProjectRole: string
{
    case OWNER = 'owner';
    case MANAGER = 'manager';
    case MEMBER = 'member';
    case VIEWER = 'viewer';

    public function label(): string
    {
        return match ($this) {
            self::OWNER => 'Pemilik',
            self::MANAGER => 'Manajer',
            self::MEMBER => 'Anggota',
            self::VIEWER => 'Peninjau',
        };
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $role) => ['value' => $role->value, 'label' => $role->label()], self::cases());
    }

    /**
     * @return array<int, self>
     */
    public static function assignable(): array
    {
        return [self::MANAGER, self::MEMBER, self::VIEWER];
    }
}
