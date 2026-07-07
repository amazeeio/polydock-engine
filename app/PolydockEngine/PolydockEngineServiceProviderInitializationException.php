<?php

declare(strict_types=1);

namespace App\PolydockEngine;

use Exception;
use Throwable;

class PolydockEngineServiceProviderInitializationException extends Exception
{
    public function __construct(
        string $message = 'ServiceProvider initialization failed',
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
