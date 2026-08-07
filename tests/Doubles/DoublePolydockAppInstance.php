<?php

declare(strict_types=1);

namespace Tests\Doubles;

use App\Polydock\Core\Enums\PolydockAppInstanceStatus;
use App\Polydock\Core\PolydockAppInstanceInterface;
use App\Polydock\Core\PolydockAppInterface;
use App\Polydock\Core\PolydockAppLoggerInterface;
use App\Polydock\Core\PolydockEngineInterface;

/**
 * Minimal in-memory PolydockAppInstanceInterface double for exercising app
 * traits without the Eloquent model or a database.
 */
class DoublePolydockAppInstance implements PolydockAppInstanceInterface
{
    public $data = [];

    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(public $storeApp = null, array $data = [])
    {
        $this->data = $data;
    }

    public function getKeyValue(string $key): mixed
    {
        if ($key === 'secret') {
            return $this->data['secret'] ?? [];
        }
        if ($key === 'amazee-ai-generated-credentials') {
            return $this->data['amazee-ai-generated-credentials'] ?? null;
        }

        return $this->data[$key] ?? '';
    }

    public function getPolydockVariableValue(string $key, $default = '')
    {
        return $this->data[$key] ?? $default;
    }

    public function setApp(PolydockAppInterface $app): self
    {
        return $this;
    }

    public function getApp(): PolydockAppInterface
    {
        throw new \Exception;
    }

    public function setName(string $name): self
    {
        return $this;
    }

    public function getName(): string
    {
        return '';
    }

    public function setAppType(string $appType): self
    {
        return $this;
    }

    public function getAppType(): string
    {
        return '';
    }

    public function getStatus(): PolydockAppInstanceStatus
    {
        throw new \Exception;
    }

    public function setStatus(PolydockAppInstanceStatus $status, string $statusMessage = ''): self
    {
        return $this;
    }

    public function setStatusMessage(string $statusMessage): self
    {
        return $this;
    }

    public function getStatusMessage(): string
    {
        return '';
    }

    public function storeKeyValue(string $key, mixed $value): self
    {
        $this->data[$key] = $value;

        return $this;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function info(string $message, array $context = []): self
    {
        return $this;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function error(string $message, array $context = []): self
    {
        return $this;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function warning(string $message, array $context = []): self
    {
        return $this;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function debug(string $message, array $context = []): self
    {
        return $this;
    }

    public function getLogger(): PolydockAppLoggerInterface
    {
        throw new \Exception;
    }

    public function setLogger(PolydockAppLoggerInterface $logger): self
    {
        return $this;
    }

    public function setEngine(PolydockEngineInterface $engine): self
    {
        return $this;
    }

    public function getEngine(): PolydockEngineInterface
    {
        throw new \Exception;
    }

    public function generateUniqueProjectName(string $prefix): string
    {
        return '';
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function save(array $options = []) {}

    public function setAppUrl(string $url, ?string $oneTimeLoginUrl = null, ?int $numberOfHoursForOneTimeLoginUrl = 24): self
    {
        return $this;
    }

    public function setOneTimeLoginUrl(string $url, int $numberOfHours = 24, bool $setOnlyDontSave = false): self
    {
        return $this;
    }

    public function getGeneratedAppAdminUsername(): string
    {
        return '';
    }

    public function getGeneratedAppAdminPassword(): string
    {
        return '';
    }
}
