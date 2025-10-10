<?php

namespace Tests\Doubles;

class AlphaTestPolydockServiceProvider extends BaseTestPolydockServiceProvider
{
    public function getName(): string
    {
        return 'Alpha Test Provider';
    }

    public function getDescription(): string
    {
        return 'Alpha Test Provider Description';
    }
}
