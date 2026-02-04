<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum PolydockVariableScopeEnum: string implements HasColor, HasIcon, HasLabel
{
    case GLOBAL = 'global';
    case BUILD = 'build';
    case RUNTIME = 'runtime';
    case POLYDOCK = 'polydock';

    public function getLabel(): string
    {
        return match($this) {
            self::GLOBAL => 'Global',
            self::BUILD => 'Build',
            self::RUNTIME => 'Runtime',
            self::POLYDOCK => 'Polydock',
        };
    }

    public function getColor(): string|array|null
    {
        return match($this) {
            self::GLOBAL => 'info',
            self::BUILD => 'warning',
            self::RUNTIME => 'success',
            self::POLYDOCK => 'primary',
        };
    }

    public function getIcon(): ?string
    {
        return match($this) {
            self::GLOBAL => 'heroicon-o-globe-alt',
            self::BUILD => 'heroicon-o-wrench-screwdriver',
            self::RUNTIME => 'heroicon-o-cpu-chip',
            self::POLYDOCK => 'heroicon-o-cog',
        };
    }

    public static function getValues(): array
    {
        return array_column(self::cases(), 'value');
    }
} 