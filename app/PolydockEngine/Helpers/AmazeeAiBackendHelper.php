<?php

namespace App\PolydockEngine\Helpers;

use App\PolydockEngine\PolydockLogger;
use App\PolydockServiceProviders\PolydockServiceProviderAmazeeAiBackend;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AmazeeAiBackendHelper
{
    public static $cacheKeyPrefix = 'amazee_ai_backend_region_';

    public static $cacheTTL = 60;

    public static function getAmazeeAiBackendRegion($regionId)
    {
        $cacheKey = self::$cacheKeyPrefix.$regionId;
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        try {
            $serviceProvider = new PolydockServiceProviderAmazeeAiBackend(
                config('polydock.service_providers_singletons.PolydockServiceProviderAmazeeAiBackend'),
                new PolydockLogger
            );

            $AmazeeAiBackendClient = $serviceProvider->getAmazeeAiBackendClient();
            $region = $AmazeeAiBackendClient->getRegion($regionId);
            Cache::put($cacheKey, $region, self::$cacheTTL);

            return $region;
        } catch (\Exception $e) {
            Log::error('Error getting Amazee AI Backend region '.$regionId.': '.$e->getMessage());

            return null;
        }
    }

    public static function getAmazeeAiBackendCodeDataValueForRegion(string $regionId, string $key): ?string
    {
        Log::info('Getting Amazee AI Backend code data value for region '.$regionId.' and key '.$key);
        $region = self::getAmazeeAiBackendRegion($regionId);

        return $region[$key] ?? null;
    }

    public static function getDataForPrivateGPTSettings(): array
    {
        return config('polydock.amazee_ai_backend_private_gpt_settings') ?? [];
    }
}
