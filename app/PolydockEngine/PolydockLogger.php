<?php

declare(strict_types=1);

namespace App\PolydockEngine;

use FreedomtechHosting\PolydockApp\PolydockAppLoggerInterface;
use Illuminate\Support\Facades\Log;

class PolydockLogger implements PolydockAppLoggerInterface
{
    /**
     * Create a new logger instance.
     */
    public function __construct(
        private readonly string $channel = 'polydock',
    ) {}

    /**
     * Log an error message
     */
    public function error(string $message, array $context = []): void
    {
        Log::channel($this->channel)->error($message, $context);
    }

    /**
     * Log a warning message
     */
    public function warning(string $message, array $context = []): void
    {
        Log::channel($this->channel)->warning($message, $context);
    }

    /**
     * Log an info message
     */
    public function info(string $message, array $context = []): void
    {
        Log::channel($this->channel)->info($message, $context);
    }

    /**
     * Log a debug message
     */
    public function debug(string $message, array $context = []): void
    {
        Log::channel($this->channel)->debug($message, $context);
    }
}
