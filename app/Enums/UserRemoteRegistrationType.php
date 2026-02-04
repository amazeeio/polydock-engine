<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum UserRemoteRegistrationType: string implements HasColor, HasIcon, HasLabel
{
    case TEST_FAIL = 'TEST_FAIL';
    case REQUEST_TRIAL = 'REQUEST_TRIAL';
    case REQUEST_TRIAL_UNLISTED_REGION = 'REQUEST_TRIAL_UNLISTED_REGION';

    public function getLabel(): string
    {
        return match ($this) {
            self::TEST_FAIL => 'Test Fail',
            self::REQUEST_TRIAL => 'Request Trial',
            self::REQUEST_TRIAL_UNLISTED_REGION => 'Request Trial (Unlisted Region)',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::TEST_FAIL => 'danger',
            self::REQUEST_TRIAL => 'success',
            self::REQUEST_TRIAL_UNLISTED_REGION => 'warning',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::TEST_FAIL => 'heroicon-o-x-circle',
            self::REQUEST_TRIAL => 'heroicon-o-beaker',
            self::REQUEST_TRIAL_UNLISTED_REGION => 'heroicon-o-globe-alt',
        };
    }

    public static function getValues(): array
    {
        return array_column(self::cases(), 'value');
    }
}
