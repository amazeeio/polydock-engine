<?php

namespace Tests\Doubles;

use FreedomtechHosting\PolydockApp\PolydockAppLoggerInterface;
use FreedomtechHosting\PolydockApp\PolydockServiceProviderInterface;

abstract class BaseTestPolydockServiceProvider implements PolydockServiceProviderInterface
{
    private array $config;
    private PolydockAppLoggerInterface $logger;

    public function __construct(array $config, PolydockAppLoggerInterface $logger)
    {
        $this->config = $config;
        $this->logger = $logger;
    }

    abstract public function getName(): string;
    abstract public function getDescription(): string;

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

    public function info(string $message, array $context = []): self
    {
        $this->logger->info($message, $context);
        return $this;
    }

    public function error(string $message, array $context = []): self
    {
        $this->logger->error($message, $context);
        return $this;
    }

    public function warning(string $message, array $context = []): self
    {
        $this->logger->warning($message, $context);
        return $this;
    }

    public function debug(string $message, array $context = []): self
    {
        $this->logger->debug($message, $context);
        return $this;
    }
} 