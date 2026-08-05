<?php

namespace Tests\Doubles;

use App\Polydock\Core\PolydockAppLoggerInterface;
use App\Polydock\Core\PolydockServiceProviderInterface;

abstract class BaseTestPolydockServiceProvider implements PolydockServiceProviderInterface
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        private readonly array $config,
        private PolydockAppLoggerInterface $logger,
    ) {}

    abstract public function getName(): string;

    abstract public function getDescription(): string;

    /**
     * @return array<string, mixed>
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    public function getLogger(): PolydockAppLoggerInterface
    {
        return $this->logger;
    }

    public function setLogger(PolydockAppLoggerInterface $logger): self
    {
        $this->logger = $logger;

        return $this;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function info(string $message, array $context = []): self
    {
        $this->logger->info($message, $context);

        return $this;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function error(string $message, array $context = []): self
    {
        $this->logger->error($message, $context);

        return $this;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function warning(string $message, array $context = []): self
    {
        $this->logger->warning($message, $context);

        return $this;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function debug(string $message, array $context = []): self
    {
        $this->logger->debug($message, $context);

        return $this;
    }
}
