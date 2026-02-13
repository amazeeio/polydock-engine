<?php

declare(strict_types=1);

namespace App\PolydockEngine;

class PolydockEngineAppNotFoundException extends \Exception
{
    public function __construct(string $appClass)
    {
        parent::__construct('App class '.$appClass.' not found');
    }
}
