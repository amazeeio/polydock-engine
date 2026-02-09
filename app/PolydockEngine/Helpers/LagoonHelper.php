<?php

namespace App\PolydockEngine\Helpers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class LagoonHelper
{
    public static $cacheKeyPrefix = 'lagoon_core_data_for_region_';

    public static $cacheTTL = 60;

    public static function getLagoonCoreDataForRegion(string $regionId): ?array
    {
        $cacheKey = self::$cacheKeyPrefix.$regionId;
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $FTLAGOON_ENDPOINT = env('FTLAGOON_ENDPOINT', 'https://api.lagoon.amazeeio.cloud/graphql');
        $allLagoonCoresData = config('polydock.lagoon_cores');
        $lagoonCoreData = $allLagoonCoresData[$FTLAGOON_ENDPOINT] ?? null;

        if (! $lagoonCoreData) {
            Log::error('No lagoon core data found for endpoint '.$FTLAGOON_ENDPOINT);

            return null;
        }

        $lagoonCoreDataForRegion = $lagoonCoreData['lagoon_deploy_regions'][$regionId] ?? null;
        if (! $lagoonCoreDataForRegion) {
            Log::error('No lagoon core data found for region '.$regionId.' and endpoint '.$FTLAGOON_ENDPOINT);

            return null;
        }

        Cache::put($cacheKey, $lagoonCoreDataForRegion, self::$cacheTTL);

        return $lagoonCoreDataForRegion;
    }

    public static function getLagoonCodeDataValueForRegion(string $regionId, string $key): ?string
    {
        $lagoonCoreDataForRegion = self::getLagoonCoreDataForRegion($regionId);

        return $lagoonCoreDataForRegion[$key] ?? null;
    }

    public static function getPublicKeyFromPrivateKey(string $privateKey): ?string
    {
        if (empty($privateKey)) {
            return null;
        }

        $tempFile = tempnam(sys_get_temp_dir(), 'pk_');
        file_put_contents($tempFile, $privateKey);
        chmod($tempFile, 0600);

        try {
            $result = \Illuminate\Support\Facades\Process::run(['ssh-keygen', '-y', '-f', $tempFile]);

            if ($result->successful()) {
                return trim($result->output());
            }

            Log::error('ssh-keygen failed: '.$result->errorOutput());
        } catch (\Throwable $e) {
            Log::error('Error generating public key: '.$e->getMessage());
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }

        return null;
    }
}
