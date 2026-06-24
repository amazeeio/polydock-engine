<?php

declare(strict_types=1);

namespace App\Polydock\Core\Exceptions;

use Exception;

class PolydockEngineValidationException extends Exception
{
    /**
     * Create a new PolydockEngineValidationException instance
     *
     * @param  string  $message  The exception message
     * @param  int  $code  The exception code
     * @param  Exception|null  $previous  The previous throwable used for exception chaining
     */
    public function __construct(string $message = '', int $code = 0, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
