<?php

declare(strict_types=1);

namespace App\Support;

final class Environment
{
    /**
     * Production on any host. Lagoon sets APP_ENV to the branch name (main, dev),
     * which Horizon's per-environment config relies on, so there the Lagoon
     * environment type is the signal; elsewhere APP_ENV=production is. Either one
     * makes it production, so a misconfiguration can only make it stricter.
     */
    public static function isProduction(): bool
    {
        return app()->environment('production', 'prod')
            || config('polydock.lagoon_environment_type') === 'production';
    }
}
