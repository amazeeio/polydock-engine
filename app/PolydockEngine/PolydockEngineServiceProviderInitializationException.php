<?php

namespace App\PolydockEngine;


class PolydockEngineServiceProviderInitializationException extends \Exception
{
    public function __construct(string $message = "ServiceProvider initialization failed", int $code = 0, \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}   