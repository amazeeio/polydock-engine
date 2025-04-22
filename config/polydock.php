<?php

return [
    'max_per_run_dispatch_midtrial_emails' => env('POLYDOCK_MAX_PER_RUN_DISPATCH_MIDTRIAL_EMAILS', 25),
    'max_per_run_dispatch_one_day_left_emails' => env('POLYDOCK_MAX_PER_RUN_DISPATCH_ONE_DAY_LEFT_EMAILS', 25),
    'max_per_run_dispatch_trial_complete_emails' => env('POLYDOCK_MAX_PER_RUN_DISPATCH_TRIAL_COMPLETE_EMAILS', 25),
    'max_per_run_dispatch_trial_complete_stage_removal' => env('POLYDOCK_MAX_PER_RUN_DISPATCH_TRIAL_COMPLETE_STAGE_REMOVAL', 5),
    'redirect_landing_page_to' => env('POLYDOCK_REDIRECT_LANDING_PAGE_TO', "https://freedomtech.hosting/"),
    'register_only_captures' => env('POLYDOCK_REGISTER_ONLY_CAPTURES', false),
    'register_simulate_round_robin' => env('POLYDOCK_REGISTER_SIMULATE_ROUND_ROBIN', false),
    'register_simulate_error' => env('POLYDOCK_REGISTER_SIMULATE_ERROR', false),
    'lagoon_deploy_private_key_file' => env('POLYDOCK_LAGOON_DEPLOY_PRIVATE_KEY_FILE', 'tests/fixtures/lagoon-deploy-private-key'),
    'service_providers_singletons' => [
         "PolydockServiceProviderFTLagoon" => [
            'class' => App\PolydockServiceProviders\PolydockServiceProviderFTLagoon::class,
            'debug' => true,
            'token_cache_dir' => env('FTLAGOON_TOKEN_CACHE_DIR', storage_path('ftlagoon/.tokencache/')),
            'ssh_private_key_file' => env('FTLAGOON_PRIVATE_KEY_FILE', 'tests/fixtures/lagoon-private-key'),
            'ssh_user' => env('FTLAGOON_SSH_USER','lagoon'),
            'ssh_server' => env('FTLAGOON_SSH_SERVER','ssh.lagoon.amazeeio.cloud'),
            'ssh_port' => env('FTLAGOON_SSH_PORT','32222'),
            'endpoint' => env('FTLAGOON_ENDPOINT','https://api.lagoon.amazeeio.cloud/graphql'),
        ],
        "PolydockServiceProviderAmazeeAiBackend" => [
            'class' => App\PolydockServiceProviders\PolydockServiceProviderAmazeeAiBackend::class,
            'debug' => true,
            'base_url' => env('AMAZEE_AI_BACKEND_BASE_URL', 'https://backend.main.amazeeai.us2.amazee.io'),
            'token_file' => env('AMAZEE_AI_BACKEND_TOKEN_FILE', storage_path('amazee-ai-backend/token')),
        ]
    ],
    'lagoon_cores' => [
        'https://api.main.lagoon-core.test6.amazee.io/graphql' => [
            'lagoon_deploy_regions' => [
                '1' => [
                    'id' => '1',
                    'code' => 'test6',
                    'provider' => 'AWS',
                    'name' => 'Test Cluster (test6)',
                    'pattern' => 'lagoon-core.test6.amazee.io',
                    'country' => 'Switzerland',
                    'country_code' => 'CH',
                ]
            ]
        ],
        'https://api.lagoon.amazeeio.cloud/graphql' => [
            'lagoon_deploy_regions' => [
                '132' => [
                    'id' => '132',
                    'code' => 'AU2',
                    'provider' => 'AWS',
                    'name' => 'Australia',
                    'pattern' => 'au2.amazee.io',
                    'country' => 'Australia',
                    'country_code' => 'AU',
                ],
                '131' => [
                    'id' => '131',
                    'code' => 'CH4',
                    'provider' => 'GCP',
                    'name' => 'Switzerland',
                    'pattern' => 'ch4.amazee.io',
                    'country' => 'Switzerland',
                    'country_code' => 'CH',
                ],
                '115' => [
                    'id' => '115',
                    'code' => 'DE3',
                    'provider' => 'AWS',
                    'name' => 'Germany',
                    'pattern' => 'de3.amazee.io',
                    'country' => 'Germany',
                    'country_code' => 'DE',
                ],
                '135' => [
                    'id' => '135',
                    'code' => 'FI2',
                    'provider' => 'GCP',
                    'name' => 'Finland',
                    'pattern' => 'fi2.amazee.io',
                    'country' => 'Finland',
                    'country_code' => 'FI',
                ],
                '122' => [
                    'id' => '122',
                    'code' => 'UK3',
                    'provider' => 'AWS',
                    'name' => 'United Kingdom',
                    'pattern' => 'uk3.amazee.io',
                    'country' => 'United Kingdom',
                    'country_code' => 'UK',
                ],
                '126' => [
                    'id' => '126',
                    'code' => 'US2',
                    'provider' => 'AWS',
                    'name' => 'United States',
                    'pattern' => 'us2.amazee.io',
                    'country' => 'United States',
                    'country_code' => 'US',
                ],
                '141' => [
                    'id' => '141',
                    'code' => 'US3',
                    'provider' => 'GCP',
                    'name' => 'United States',
                    'pattern' => 'us3.amazee.io',
                    'country' => 'United States',
                    'country_code' => 'US',
                ]
            ]
        ]
    ]
];
