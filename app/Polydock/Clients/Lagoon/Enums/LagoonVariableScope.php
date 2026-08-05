<?php

namespace App\Polydock\Clients\Lagoon\Enums;

enum LagoonVariableScope: string
{
    case GLOBAL = 'GLOBAL';
    case RUNTIME = 'RUNTIME';
    case BUILD = 'BUILD';
    case CONTAINER_REGISTRY = 'CONTAINER_REGISTRY';

    /**
     * @return array<int, string>
     */
    public static function getValues(): array
    {
        return array_column(self::cases(), 'value');
    }
}
