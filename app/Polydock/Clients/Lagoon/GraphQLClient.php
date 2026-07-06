<?php

declare(strict_types=1);

namespace App\Polydock\Clients\Lagoon;

use Illuminate\Support\Facades\Http;

class GraphQLClient
{
    public function __construct(
        private readonly string $endpoint,
        private readonly string $token,
        private readonly array $config = []
    ) {}

    public function query(string $query, array $variables = []): GraphQLResponse
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$this->token,
        ])
            ->timeout($this->config['timeout'] ?? 10.0)
            ->connectTimeout($this->config['connect_timeout'] ?? 5.0)
            ->post($this->endpoint, [
                'query' => $query,
                'variables' => $variables,
            ]);

        $body = $response->json() ?? [];
        $data = $body['data'] ?? [];
        $errors = $body['errors'] ?? [];

        return new GraphQLResponse($data, $errors);
    }
}
