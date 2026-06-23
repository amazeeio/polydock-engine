<?php

declare(strict_types=1);

namespace App\Polydock\Core;

use App\Polydock\Core\Exceptions\PolydockEngineProcessPolydockAppInstanceException;

interface PolydockEngineInterface
{
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

    /**
     * Get a polydock service provider singleton instance
     *
     * @param  string  $serviceProviderName  The name of the service provider
     * @return PolydockServiceProviderInterface The service provider instance
     */
    public function getPolydockServiceProviderSingletonInstance(string $serviceProviderName): PolydockServiceProviderInterface;

    /**
     * Validate that an app instance has all required variables
     *
     * @param  PolydockAppInstanceInterface  $appInstance  The app instance to validate
     * @return bool True if the app instance has all required variables, false otherwise
     */
    public function validateAppInstanceHasAllRequiredVariables(PolydockAppInstanceInterface $appInstance): bool;

    /**
     * Process an app instance
     *
     * @param  PolydockAppInstanceInterface  $appInstance  The app instance to process
     *
     * @throws PolydockEngineProcessPolydockAppInstanceException If the app instance cannot be processed
     */
    public function processPolydockAppInstance(PolydockAppInstanceInterface $appInstance): PolydockAppInstanceInterface;
}
