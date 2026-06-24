<?php

declare(strict_types=1);

namespace App\Polydock\Core;

interface PolydockServiceProviderInterface
{
    /**
     * Constructor
     *
     * @param  array  $config  The configuration for the service provider
     */
    public function __construct(array $config, PolydockAppLoggerInterface $logger);

    /**
     * Get the name of the service provider
     *
     * @return string The name of the service provider
     */
    public function getName(): string;

    /**
     * Get the description of the service provider
     *
     * @return string The description of the service provider
     */
    public function getDescription(): string;

    /**
     * Set the logger instance
     *
     * @param  PolydockAppLoggerInterface  $logger  The logger instance
     * @return self Returns the instance for method chaining
     */
    public function setLogger(PolydockAppLoggerInterface $logger): self;

    /**
     * Get the logger instance
     *
     * @return PolydockAppLoggerInterface The logger instance
     */
    public function getLogger(): PolydockAppLoggerInterface;

    /**
     * Log an informational message
     *
     * @param  string  $message  The message to log
     * @param  array  $context  Additional context data for the log entry
     * @return self Returns the instance for method chaining
     */
    public function info(string $message, array $context = []): self;

    /**
     * Log an error message
     *
     * @param  string  $message  The message to log
     * @param  array  $context  Additional context data for the log entry
     * @return self Returns the instance for method chaining
     */
    public function error(string $message, array $context = []): self;

    /**
     * Log a warning message
     *
     * @param  string  $message  The message to log
     * @param  array  $context  Additional context data for the log entry
     * @return self Returns the instance for method chaining
     */
    public function warning(string $message, array $context = []): self;

    /**
     * Log a debug message
     *
     * @param  string  $message  The message to log
     * @param  array  $context  Additional context data for the log entry
     * @return self Returns the instance for method chaining
     */
    public function debug(string $message, array $context = []): self;
}
