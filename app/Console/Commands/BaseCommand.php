<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

abstract class BaseCommand extends Command
{
    /**
     * Get the list of arguments or options that contain sensitive data and must be redacted from logs.
     *
     * @return array<string>
     */
    public function sensitiveInputs(): array
    {
        return [];
    }
}
