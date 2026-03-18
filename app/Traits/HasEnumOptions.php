<?php

declare(strict_types=1);

namespace App\Traits;

use Filament\Support\Contracts\HasLabel;

trait HasEnumOptions
{
    public static function getEnumOptions(): array
    {
        return collect(static::cases())
            ->mapWithKeys(function ($case) {
                // Fallback to title-cased value if the Enum doesn't implement HasLabel
                $label = ($case instanceof HasLabel)
                    ? $case->getLabel()
                    : str($case->value)->title()->toString();

                return [$case->value => $label];
            })->all();
    }

    public static function getValues(): array
    {
        return array_column(static::cases(), 'value');
    }
}