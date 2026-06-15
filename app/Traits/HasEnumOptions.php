<?php

declare(strict_types=1);

namespace App\Traits;

use App\Support\EnumHelper;

trait HasEnumOptions
{
    public static function getEnumOptions(): array
    {
        return EnumHelper::getEnumOptions(static::class);
    }

    public static function getValues(): array
    {
        return array_column(static::cases(), 'value');
    }
}
