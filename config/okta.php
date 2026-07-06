<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Okta-forced email domains
    |--------------------------------------------------------------------------
    |
    | Users whose email domain is in this list must log in via Okta OIDC and
    | never see a password field. Only applies when Okta is configured
    | (services.okta.client_id is set). Comma-separated, case-insensitive.
    |
    */

    'domains' => array_filter(array_map(
        fn (string $domain): string => strtolower(trim($domain)),
        explode(',', (string) env('OKTA_DOMAINS', 'amazee.io')),
    )),

];
