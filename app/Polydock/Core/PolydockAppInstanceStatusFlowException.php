<?php

declare(strict_types=1);

namespace App\Polydock\Core;

use Exception;
use Throwable;

/**
 * Exception thrown when there is an issue with the status flow of a Polydock app instance
 */
class PolydockAppInstanceStatusFlowException extends Exception
{
    /**
     * Constructor for PolydockAppInstanceStatusFlowException
     *
     * @param  string  $message  The error message
     * @param  int  $code  The error code
     * @param  Throwable|null  $previous  The previous exception (optional)
     */
    public function __construct(string $message, int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
