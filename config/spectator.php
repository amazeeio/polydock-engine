<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Spec Source
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default spec source that should be used
    | by the framework.
    |
    */

    'default' => env('SPEC_SOURCE', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Sources
    |--------------------------------------------------------------------------
    |
    | Here you may configure as many sources as you wish, and you
    | may even configure multiple source of the same type. Defaults have
    | been setup for each driver as an example of the required options.
    |
    */

    'sources' => [
        'local' => [
            'source' => 'local',
            // Default to the vendored spec directory so tests work without
            // env setup: the engine's own spec is exported there via
            // `composer spec:export`; consumed-API specs (e.g. api.amazee.ai)
            // are vendored alongside and selected per-test with
            // Spectator::using('<file>.json').
            'base_path' => env('SPEC_PATH', base_path('tests/fixtures/openapi')),
        ],

        'remote' => [
            'source' => 'remote',
            'base_path' => env('SPEC_PATH'),
            'params' => env('SPEC_URL_PARAMS', ''),
        ],

        'github' => [
            'source' => 'github',
            'base_path' => env('SPEC_GITHUB_PATH'),
            'repo' => env('SPEC_GITHUB_REPO'),
            'token' => env('SPEC_GITHUB_TOKEN'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Paths
    |--------------------------------------------------------------------------
    |
    | Configure path defaults, like prefixes.
    |
    */

    // Scramble exports paths relative to its `/api` server URL (spec paths
    // are `/regions`, not `/api/regions`), so requests must be matched with
    // this prefix stripped.
    'path_prefix' => 'api',

    /*
    |--------------------------------------------------------------------------
    | Middleware Groups
    |--------------------------------------------------------------------------
    |
    | Specify the groups that spectator's middleware should be prepended to.
    |
    */

    'middleware_groups' => ['api'],
];
