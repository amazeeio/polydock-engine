<?php

namespace App\Polydock\Clients\Lagoon;

use Exception;
use Throwable;

class LagoonClientPrivateKeyNotFoundException extends Exception
{
    public function __construct($sshPrivateKeyFile = '', $code = 0, ?Throwable $previous = null)
    {
        parent::__construct("Private key not found: $sshPrivateKeyFile", $code, $previous);
    }
}
