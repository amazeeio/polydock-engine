<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum PolydockStoreAppStatusEnum: string implements HasColor, HasIcon, HasLabel
{
    case AVAILABLE = 'available';
    case UNAVAILABLE = 'unavailable';

    public function getLabel(): string
    {
        return match ($this) {
            self::AVAILABLE => 'Available',
            self::UNAVAILABLE => 'Unavailable',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::AVAILABLE => 'success',
            self::UNAVAILABLE => 'danger',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::AVAILABLE => 'heroicon-o-check-circle',
            self::UNAVAILABLE => 'heroicon-o-x-circle',
        };
    }

    public static function getValues(): array
    {
        return array_column(self::cases(), 'value');
    }
}
