<?php

namespace App\Enums;

enum UserGroupRoleEnum: string
{
    case OWNER = 'owner';
    case MEMBER = 'member';
    case VIEWER = 'viewer';

    public function getLabel(): string
    {
        return match($this) {
            self::OWNER => 'Owner',
            self::MEMBER => 'Member', 
            self::VIEWER => 'Viewer',
        };
    }

    public static function getValues(): array
    {
        return array_column(self::cases(), 'value');
    }
}
