<?php

declare(strict_types=1);

namespace App\PolydockEngine;

use Exception;

class PolydockEngineServiceProviderNotFoundException extends Exception
{
    public function __construct(string $polydockServiceProviderClass)
    {
        parent::__construct('Service provider class '.$polydockServiceProviderClass.' not found');
    }
}
