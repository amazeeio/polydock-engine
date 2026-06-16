<?php

declare(strict_types=1);

namespace App\Support;

use Filament\Support\Contracts\HasLabel;

class EnumHelper
{
    /**
     * Get the value => label options map for any string-backed PHP Enum class.
     *
     * @param  class-string<\BackedEnum>  $enumClass
     * @return array<string, string>
     */
    public static function getEnumOptions(string $enumClass): array
    {
        return collect($enumClass::cases())
            ->mapWithKeys(function ($case) {
                $label = ($case instanceof HasLabel)
                    ? $case->getLabel()
                    : str($case->value)->title()->toString();

                return [$case->value => $label];
            })->all();
    }
}
