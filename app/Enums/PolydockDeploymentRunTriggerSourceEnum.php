<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\HasEnumOptions;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum PolydockDeploymentRunTriggerSourceEnum: string implements HasColor, HasLabel
{
    use HasEnumOptions;

    case SCHEDULED = 'scheduled';
    case MANUAL = 'manual';
    case BETA = 'beta';

    public function getLabel(): string
    {
        return match ($this) {
            self::SCHEDULED => 'Scheduled',
            self::MANUAL => 'Manual',
            self::BETA => 'Beta',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::SCHEDULED => 'info',
            self::MANUAL => 'primary',
            self::BETA => 'warning',
        };
    }

    public static function getValues(): array
    {
        return array_column(self::cases(), 'value');
    }
}
