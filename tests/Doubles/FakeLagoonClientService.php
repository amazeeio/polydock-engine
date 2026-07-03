<?php

declare(strict_types=1);

namespace Tests\Doubles;

use App\Polydock\Clients\Lagoon\Client;
use App\Services\LagoonClientService;

/**
 * Test double for LagoonClientService that always returns a FakeLagoonClient,
 * bypassing SSH authentication.
 */
class FakeLagoonClientService extends LagoonClientService
{
    public function __construct(public FakeLagoonClient $fakeClient) {}

    #[\Override]
    public function getAuthenticatedClient(array $overrides = []): Client
    {
        return $this->fakeClient;
    }
}
