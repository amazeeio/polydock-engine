<?php

namespace Tests\Unit\PolydockEngine;

use App\PolydockEngine\Engine;
use FreedomtechHosting\PolydockApp\PolydockAppLoggerInterface;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\Doubles\AlphaTestPolydockServiceProvider;
use Tests\Doubles\BetaTestPolydockServiceProvider;
use Tests\TestCase;

class PolydockEngineTest extends TestCase
{
    private Engine $engine;

    private PolydockAppLoggerInterface $logger;

    private array $testConfig;

    protected function setUp(): void
    {
        parent::setUp();

        // Create mock logger with basic method stubs
        $this->logger = Mockery::mock(PolydockAppLoggerInterface::class);
        $this->logger->shouldReceive('info')->andReturnSelf();
        $this->logger->shouldReceive('error')->andReturnSelf();
        $this->logger->shouldReceive('debug')->andReturnSelf();
        $this->logger->shouldReceive('warning')->andReturnSelf();

        // Create test config
        $this->testConfig = [
            AlphaTestPolydockServiceProvider::class => [
                'class' => AlphaTestPolydockServiceProvider::class,
                'key' => 'test_key_alpha',
                'secret' => 'test_secret_alpha',
                'region' => 'test_region_alpha',
            ],
            BetaTestPolydockServiceProvider::class => [
                'class' => BetaTestPolydockServiceProvider::class,
                'key' => 'test_key_beta',
                'secret' => 'test_secret_beta',
                'region' => 'test_region_beta',
            ],
        ];

        $this->engine = new Engine($this->logger, $this->testConfig);
    }

    #[Test]
    public function it_logs_info_messages()
    {
        $result = $this->engine->info('Test info message', ['context' => 'test']);
        $this->assertInstanceOf(Engine::class, $result);
        $this->assertSame($this->engine, $result);
    }

    #[Test]
    public function it_logs_error_messages()
    {
        $result = $this->engine->error('Test error message', ['context' => 'test']);
        $this->assertInstanceOf(Engine::class, $result);
        $this->assertSame($this->engine, $result);
    }

    #[Test]
    public function it_logs_debug_messages()
    {
        $result = $this->engine->debug('Test debug message', ['context' => 'test']);
        $this->assertInstanceOf(Engine::class, $result);
        $this->assertSame($this->engine, $result);
    }

    #[Test]
    public function it_logs_warning_messages()
    {
        $result = $this->engine->warning('Test warning message', ['context' => 'test']);
        $this->assertInstanceOf(Engine::class, $result);
        $this->assertSame($this->engine, $result);
    }

    #[Test]
    public function it_creates_service_provider_with_config()
    {
        $provider = new AlphaTestPolydockServiceProvider($this->testConfig[AlphaTestPolydockServiceProvider::class], $this->logger);

        $this->assertEquals($this->testConfig[AlphaTestPolydockServiceProvider::class], $provider->getConfig());
        $this->assertSame($this->logger, $provider->getLogger());
    }

    #[Test]
    public function it_returns_service_provider_instance()
    {
        $provider = $this->engine->getPolydockServiceProviderSingletonInstance(AlphaTestPolydockServiceProvider::class);

        $this->assertInstanceOf(AlphaTestPolydockServiceProvider::class, $provider);
        $this->assertSame($this->logger, $provider->getLogger());
    }

    #[Test]
    public function it_returns_same_service_provider_instance_for_same_key()
    {
        $firstInstance = $this->engine->getPolydockServiceProviderSingletonInstance(AlphaTestPolydockServiceProvider::class);
        $secondInstance = $this->engine->getPolydockServiceProviderSingletonInstance(AlphaTestPolydockServiceProvider::class);

        $this->assertSame($firstInstance, $secondInstance);
    }

    #[Test]
    public function it_returns_different_service_provider_instances_for_different_keys()
    {
        $firstInstance = $this->engine->getPolydockServiceProviderSingletonInstance(AlphaTestPolydockServiceProvider::class);
        $secondInstance = $this->engine->getPolydockServiceProviderSingletonInstance(BetaTestPolydockServiceProvider::class);

        $this->assertNotSame($firstInstance, $secondInstance);
        $this->assertInstanceOf(AlphaTestPolydockServiceProvider::class, $firstInstance);
        $this->assertInstanceOf(BetaTestPolydockServiceProvider::class, $secondInstance);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
