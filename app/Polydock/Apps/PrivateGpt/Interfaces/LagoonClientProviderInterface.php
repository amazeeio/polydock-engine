<?php

declare(strict_types=1);

namespace App\Polydock\Apps\PrivateGpt\Interfaces;

use App\Polydock\Clients\Lagoon\Client as LagoonClient;
use App\Polydock\Core\PolydockServiceProviderInterface;

interface LagoonClientProviderInterface extends PolydockServiceProviderInterface
{
    public function getLagoonClient(): LagoonClient;
}
