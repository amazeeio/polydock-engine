<?php

namespace App\PolydockEngine;

class PolydockEngineServiceProviderNotFoundException extends \Exception
{
    public function __construct(string $polydockServiceProviderClass)
    {
        parent::__construct('Service provider class '.$polydockServiceProviderClass.' not found');
    }
}
