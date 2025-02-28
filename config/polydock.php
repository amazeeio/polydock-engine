<?php

return [
    'redirect_landing_page_to' => env('POLYDOCK_REDIRECT_LANDING_PAGE_TO', "https://freedomtech.hosting/"),
    'register_only_captures' => env('POLYDOCK_REGISTER_ONLY_CAPTURES', false),
    'register_simulate_round_robin' => env('POLYDOCK_REGISTER_SIMULATE_ROUND_ROBIN', false),
    'register_simulate_error' => env('POLYDOCK_REGISTER_SIMULATE_ERROR', false),
    'service_providers_singletons' => [
         "PolydockServiceProviderFTLagoon" => [
            'class' => App\PolydockServiceProviders\PolydockServiceProviderFTLagoon::class,
            'debug' => true,
            'token_cache_dir' => env('FTLAGOON_TOKEN_CACHE_DIR', 'storage/ftlagoon/.tokencache/'),
            'ssh_private_key_file' => env('FTLAGOON_PRIVATE_KEY_FILE', '/storage/ftlagoon/.ssh/id_rsa'),
            'ssh_user' => env('FTLAGOON_SSH_USER','lagoon'),
            'ssh_server' => env('FTLAGOON_SSH_SERVER','ssh.lagoon.amazeeio.cloud'),
            'ssh_port' => env('FTLAGOON_SSH_PORT','32222'),
            'endpoint' => env('FTLAGOON_ENDPOINT','https://api.lagoon.amazeeio.cloud/graphql'),
        ]
    ]
];