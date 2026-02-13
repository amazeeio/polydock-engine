<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum PolydockStoreStatusEnum: string implements HasColor, HasIcon, HasLabel
{
    case UNAVAILABLE = 'unavailable';
    case PUBLIC = 'public';
    case PRIVATE = 'private';

    public function getLabel(): string
    {
        return match ($this) {
            self::UNAVAILABLE => 'Unavailable',
            self::PUBLIC => 'Public',
            self::PRIVATE => 'Private',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::UNAVAILABLE => 'danger',
            self::PUBLIC => 'success',
            self::PRIVATE => 'warning',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::UNAVAILABLE => 'heroicon-o-x-circle',
            self::PUBLIC => 'heroicon-o-globe-alt',
            self::PRIVATE => 'heroicon-o-lock-closed',
        };
    }

    public static function getValues(): array
    {
        return array_column(self::cases(), 'value');
    }
}
