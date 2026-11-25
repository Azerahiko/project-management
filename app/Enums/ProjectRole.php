<?php

namespace App\Enums;

enum ProjectRole: string
{
    case OWNER = 'owner';
    case MANAGER = 'manager';
    case FRONTEND = 'frontend';
    case BACKEND = 'backend';
    case FULLSTACK = 'fullstack';
    case UI_UX = 'ui_ux';
    case REVIEWER = 'reviewer';
    case CLIENT = 'client';

    public function label(): string
    {
        return match ($this) {
            self::OWNER => 'Project Manager',
            self::MANAGER => 'Manajer',
            self::FRONTEND => 'Frontend',
            self::BACKEND => 'Backend',
            self::FULLSTACK => 'Fullstack',
            self::UI_UX => 'UI/UX',
            self::REVIEWER => 'Reviewer',
            self::CLIENT => 'Client',
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
        return [
            self::MANAGER,
            self::FRONTEND,
            self::BACKEND,
            self::FULLSTACK,
            self::UI_UX,
            self::REVIEWER,
            self::CLIENT,
        ];
    }
}
