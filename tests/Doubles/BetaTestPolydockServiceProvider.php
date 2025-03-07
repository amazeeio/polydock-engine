<?php

namespace Tests\Doubles;

class BetaTestPolydockServiceProvider extends BaseTestPolydockServiceProvider
{
    public function getName(): string
    {
        return 'Beta Test Provider';
    }

    public function getDescription(): string
    {
        return 'Beta Test Provider Description';
    }
} 