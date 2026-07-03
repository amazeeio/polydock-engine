<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\HasEnumOptions;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum PolydockDeploymentRunStatusEnum: string implements HasColor, HasIcon, HasLabel
{
    use HasEnumOptions;

    case PENDING = 'pending';
    case RUNNING = 'running';
    case COMPLETED = 'completed';
    case PARTIAL_FAILED = 'partial_failed';
    case FAILED = 'failed';

    public function getLabel(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::RUNNING => 'Running',
            self::COMPLETED => 'Completed',
            self::PARTIAL_FAILED => 'Partially failed',
            self::FAILED => 'Failed',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::PENDING => 'gray',
            self::RUNNING => 'info',
            self::COMPLETED => 'success',
            self::PARTIAL_FAILED => 'warning',
            self::FAILED => 'danger',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::PENDING => 'heroicon-o-clock',
            self::RUNNING => 'heroicon-o-arrow-path',
            self::COMPLETED => 'heroicon-o-check-circle',
            self::PARTIAL_FAILED => 'heroicon-o-exclamation-triangle',
            self::FAILED => 'heroicon-o-x-circle',
        };
    }

    /**
     * Statuses at which the run is finished and should no longer be polled.
     *
     * @return array<int, self>
     */
    public static function terminalStatuses(): array
    {
        return [self::COMPLETED, self::PARTIAL_FAILED, self::FAILED];
    }

    public function isTerminal(): bool
    {
        return in_array($this, self::terminalStatuses(), true);
    }

    public static function getValues(): array
    {
        return array_column(self::cases(), 'value');
    }
}
