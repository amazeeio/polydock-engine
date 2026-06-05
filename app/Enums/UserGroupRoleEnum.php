<?php

namespace App\Enums;

use App\Traits\HasEnumOptions;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum UserGroupRoleEnum: string implements HasColor, HasIcon, HasLabel
{
    use HasEnumOptions;

    case OWNER = 'owner';
    case ADMIN = 'admin';
    case MEMBER = 'member';
    case VIEWER = 'viewer';

    public function getLabel(): string
    {
        return match ($this) {
            self::OWNER => 'Owner',
            self::ADMIN => 'Admin',
            self::MEMBER => 'Member',
            self::VIEWER => 'Viewer',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::OWNER => 'danger',
            self::ADMIN => 'primary',
            self::MEMBER => 'warning',
            self::VIEWER => 'success',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::OWNER => 'heroicon-o-crown',
            self::ADMIN => 'heroicon-o-shield-check',
            self::MEMBER => 'heroicon-o-user-group',
            self::VIEWER => 'heroicon-o-eye',
        };
    }

    /**
     * Numeric hierarchy for easy comparisons.
     */
    public function level(): int
    {
        return match ($this) {
            self::VIEWER => 10,
            self::MEMBER => 20,
            self::ADMIN => 30,
            self::OWNER => 40,
        };
    }

    public function atLeast(self $required): bool
    {
        return $this->level() >= $required->level();
    }

    public static function getValues(): array
    {
        return array_column(self::cases(), 'value');
    }
}
