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

    /*
    |--------------------------------------------------------------------------
    | Okta group → role map
    |--------------------------------------------------------------------------
    |
    | On every Okta login, roles in this map are synced from the token's
    | `groups` claim: granted when the group is present, revoked when absent.
    | Roles outside this map are never touched.
    |
    */

    'group_role_map' => [
        'polydock-admins' => 'super_admin',
        'polydock-support' => 'support',
    ],

];
