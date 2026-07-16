<?php

declare(strict_types=1);

namespace App\Polydock\Core;

use App\Polydock\Core\Enums\PolydockAppInstanceStatus;

interface PolydockAppInstanceInterface
{
    /**
     * Set the app instance
     *
     * @param  PolydockAppInterface  $app  The app instance
     * @return self Returns the instance for method chaining
     */
    public function setApp(PolydockAppInterface $app): self;

    /**
     * Get the app instance
     *
     * @return PolydockAppInterface The app instance
     */
    public function getApp(): PolydockAppInterface;

    /**
     * Set the name of the app instance
     *
     * @return self Returns the instance for method chaining
     */
    public function setName(string $name): self;

    /**
     * Get the name of the instance
     *
     * @return string The name
     */
    public function getName(): string;

    /**
     * Set the type of the app instance
     *
     * @param  string  $appType  The type of the app instance
     * @return self Returns the instance for method chaining
     */
    public function setAppType(string $appType): self;

    /**
     * Get the type of the app instance
     *
     * @return string The type of the app instance
     */
    public function getAppType(): string;

    /**
     * Get the current status of the app instance
     *
     * @return PolydockAppInstanceStatus The current status enum value
     */
    public function getStatus(): PolydockAppInstanceStatus;

    /**
     * Set the status of the app instance
     *
     * @param  PolydockAppInstanceStatus  $status  The new status to set
     * @return self Returns the instance for method chaining
     */
    public function setStatus(PolydockAppInstanceStatus $status, string $statusMessage = ''): self;

    /**
     * Set the status message of the app instance
     *
     * @param  string  $statusMessage  The new status message to set
     * @return self Returns the instance for method chaining
     */
    public function setStatusMessage(string $statusMessage): self;

    /**
     * Get the status message of the app instance
     *
     * @return string The status message of the app instance
     */
    public function getStatusMessage(): string;

    /**
     * Store a key-value pair for the app instance
     *
     * @param  string  $key  The key to store
     * @param  mixed  $value  The value to store
     * @return PolydockAppInstanceInterface Returns the instance for method chaining
     */
    public function storeKeyValue(string $key, mixed $value): PolydockAppInstanceInterface;

    /**
     * Get a stored value by key
     *
     * @param  string  $key  The key to retrieve
     * @return mixed The stored value, or empty string if not found
     */
    public function getKeyValue(string $key): mixed;

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
     * Get the logger instance
     *
     * @return PolydockAppLoggerInterface The logger instance
     */
    public function getLogger(): PolydockAppLoggerInterface;

    /**
     * Set the logger instance
     *
     * @param  PolydockAppLoggerInterface  $logger  The logger instance
     * @return self Returns the instance for method chaining
     */
    public function setLogger(PolydockAppLoggerInterface $logger): self;

    /**
     * Set the engine instance
     *
     * @param  PolydockEngineInterface  $engine  The engine instance
     * @return self Returns the instance for method chaining
     */
    public function setEngine(PolydockEngineInterface $engine): self;

    /**
     * Get the engine instance
     *
     * @return PolydockEngineInterface The engine instance
     */
    public function getEngine(): PolydockEngineInterface;

    /**
     * Generate a unique project name
     *
     * @param  string  $prefix  The prefix for the project name
     * @return string The unique project name
     */
    public function generateUniqueProjectName(string $prefix): string;

    /**
     * Save the app instance
     *
     * @param  array  $options  Additional options for the save operation
     * @return mixed True if the save operation was successful, false otherwise
     */
    public function save(array $options = []);

    /**
     * Set the app URL for the app instance
     *
     * @param  string  $url  The URL to set
     * @param  string|null  $oneTimeLoginUrl  The one-time login URL to set
     * @param  int|null  $numberOfHoursForOneTimeLoginUrl  The number of hours for the one-time login URL
     * @return self Returns the instance for method chaining
     */
    public function setAppUrl(string $url, ?string $oneTimeLoginUrl = null, ?int $numberOfHoursForOneTimeLoginUrl = 24): self;

    /**
     * Set the one-time login URL for the app instance
     *
     * @param  string  $url  The URL to set
     * @param  int  $numberOfHours  The number of hours for the one-time login URL
     * @param  bool  $setOnlyDontSave  Whether to set only and not save the one-time login URL
     * @return self Returns the instance for method chaining
     */
    public function setOneTimeLoginUrl(string $url, int $numberOfHours = 24, bool $setOnlyDontSave = false): self;

    /**
     * Get the generated app admin username
     *
     * @return string The generated app admin username
     */
    public function getGeneratedAppAdminUsername(): string;

    /**
     * Get the generated app admin password
     *
     * @return string The generated app admin password
     */
    public function getGeneratedAppAdminPassword(): string;
}
