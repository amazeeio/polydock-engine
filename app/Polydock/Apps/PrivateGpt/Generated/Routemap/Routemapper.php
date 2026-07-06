<?php

declare(strict_types=1);

namespace App\Polydock\Apps\PrivateGpt\Generated\Routemap;

use InvalidArgumentException;
use JsonException;

class Routemapper
{
    public static function clusterMap(int $deployTarget): string
    {
        return match ($deployTarget) {
            1 => 'test',
            131 => 'ch4',
            115 => 'de3',
            132 => 'au2',
            126 => 'us2',
            122 => 'uk3',
            2001 => 'local',
            default => throw new InvalidArgumentException('Invalid deploy target: '.$deployTarget),
        };
    }

    public static function drupalUrl(int $deployTarget, string $projectName): string
    {
        $clusterId = self::clusterMap($deployTarget);

        return sprintf('%s.login.%s.private.amazee.ai', $projectName, $clusterId);
    }

    public static function chainlitUrl(int $deployTarget, string $projectName): string
    {
        $clusterId = self::clusterMap($deployTarget);

        return sprintf('%s.%s.private.amazee.ai', $projectName, $clusterId);
    }

    // @phpstan-ignore-next-line
    public static function deployTargetToRoutes(int $deployTarget, string $projectName): array
    {

        return ['routes' => [
            ['domain' => self::drupalUrl($deployTarget, $projectName),
                'service' => 'nginx',
            ],
            ['domain' => self::chainlitUrl($deployTarget, $projectName),
                'service' => 'chat'],
        ]];
    }

    /**
     * @throws JsonException
     */
    public static function base64encodedRoutes(int $deployTarget, string $projectName): string
    {
        $routes = self::deployTargetToRoutes($deployTarget, $projectName);

        return base64_encode(json_encode($routes, JSON_THROW_ON_ERROR));
    }
}
