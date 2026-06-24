<?php

declare(strict_types=1);

namespace App\Polydock\Core\Loggers;

use App\Polydock\Core\PolydockAppLoggerInterface;

class PolydockAppEchoLogger implements PolydockAppLoggerInterface
{
    /**
     * Log an informational message
     *
     * @param  string  $message  The message to log
     * @param  array  $context  Additional context data for the log entry
     */
    public function info(string $message, array $context = []): void
    {
        $this->output('INFO', $message, $context);
    }

    /**
     * Log an error message
     *
     * @param  string  $message  The message to log
     * @param  array  $context  Additional context data for the log entry
     */
    public function error(string $message, array $context = []): void
    {
        $this->output('ERROR', $message, $context);
    }

    /**
     * Log a warning message
     *
     * @param  string  $message  The message to log
     * @param  array  $context  Additional context data for the log entry
     */
    public function warning(string $message, array $context = []): void
    {
        $this->output('WARNING', $message, $context);
    }

    /**
     * Log a debug message
     *
     * @param  string  $message  The message to log
     * @param  array  $context  Additional context data for the log entry
     */
    public function debug(string $message, array $context = []): void
    {
        $this->output('DEBUG', $message, $context);
    }

    /**
     * Output the log message with level and context
     *
     * @param  string  $level  The log level
     * @param  string  $message  The message to log
     * @param  array  $context  Additional context data
     */
    private function output(string $level, string $message, array $context): void
    {
        echo "[$level] $message".PHP_EOL;
        if (! empty($context)) {
            echo 'Context: '.json_encode($context, JSON_PRETTY_PRINT).PHP_EOL;
        }
    }
}
