<?php

namespace App\Polydock\Clients\Lagoon;

use Exception;

class LagoonVariableScopeInvalidException extends Exception
{
    public function __construct(string $message = 'Invalid variable scope', int $code = 0, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
