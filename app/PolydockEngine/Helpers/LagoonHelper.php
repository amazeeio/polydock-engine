<?php

namespace App\PolydockEngine\Helpers;

use App\PolydockEngine\PolydockEngineServiceProviderInitializationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class LagoonHelper
{
    static $cacheKeyPrefix = 'lagoon_core_data_for_region_';
    static $cacheTTL = 60;

    public static function getLagoonCoreDataForRegion(string $regionId) : ?array
    {
        $cacheKey = self::$cacheKeyPrefix . $regionId;
        if(Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }
        
        $FTLAGOON_ENDPOINT = env('FTLAGOON_ENDPOINT','https://api.lagoon.amazeeio.cloud/graphql');
        $allLagoonCoresData = config('polydock.lagoon_cores');
        $lagoonCoreData = $allLagoonCoresData[$FTLAGOON_ENDPOINT] ?? null;

        if(!$lagoonCoreData) {
            Log::error('No lagoon core data found for endpoint ' . $FTLAGOON_ENDPOINT);
            return null;
        }

        $lagoonCoreDataForRegion = $lagoonCoreData['lagoon_deploy_regions'][$regionId] ?? null;
        if(!$lagoonCoreDataForRegion) {
            Log::error('No lagoon core data found for region ' . $regionId . ' and endpoint ' . $FTLAGOON_ENDPOINT);
            return null;
        }

        Cache::put($cacheKey, $lagoonCoreDataForRegion, self::$cacheTTL);
        return $lagoonCoreDataForRegion;
    }

    public static function getLagoonCodeDataValueForRegion(string $regionId, string $key) : string
    {
        $lagoonCoreDataForRegion = self::getLagoonCoreDataForRegion($regionId);
        return $lagoonCoreDataForRegion[$key] ?? null;
    }
}