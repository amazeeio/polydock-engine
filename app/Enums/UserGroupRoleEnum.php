<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum UserGroupRoleEnum: string implements HasColor, HasIcon, HasLabel
{
    case OWNER = 'owner';
    case MEMBER = 'member';
    case VIEWER = 'viewer';

    public function getLabel(): string
    {
        return match ($this) {
            self::OWNER => 'Owner',
            self::MEMBER => 'Member',
            self::VIEWER => 'Viewer',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::OWNER => 'danger',
            self::MEMBER => 'warning',
            self::VIEWER => 'success',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::OWNER => 'heroicon-o-crown',
            self::MEMBER => 'heroicon-o-user-group',
            self::VIEWER => 'heroicon-o-eye',
        };
    }

    public static function getValues(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function getOptions(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($role) => [
            $role->value => $role->getLabel(),
        ])->all();
    }
}
