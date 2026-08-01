<?php

declare(strict_types=1);

namespace App\Traits;

use App\Support\EnumHelper;

trait HasEnumOptions
{
    /**
     * @return array<string, mixed>
     */
    public static function getEnumOptions(): array
    {
        return EnumHelper::getEnumOptions(static::class);
    }

    /**
     * @return array<int, string>
     */
    public static function getValues(): array
    {
        return array_column(static::cases(), 'value');
    }
}
