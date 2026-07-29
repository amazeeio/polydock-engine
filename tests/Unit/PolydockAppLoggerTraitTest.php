<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Polydock\Core\Loggers\PolydockAppCacheLogger;
use App\Polydock\Core\PolydockAppLoggerInterface;
use App\Polydock\Core\Traits\PolydockAppLoggerTrait;
use App\PolydockEngine\PolydockLogger;
use App\PolydockServiceProviders\PolydockServiceProviderFTLagoon;
use Tests\TestCase;

/**
 * Regression: PolydockAppLoggerTrait::setLogger() read $this->logger for the
 * cache-flush check before the typed property was ever assigned. The FTLagoon
 * provider's constructor calls setLogger() directly, so every lifecycle job
 * fataled with "Typed property ...::$logger must not be accessed before
 * initialization".
 */
class PolydockAppLoggerTraitTest extends TestCase
{
    public function test_ftlagoon_provider_can_be_constructed_with_a_fresh_logger(): void
    {
        // The Lagoon client's constructor only checks the key file exists —
        // a throwaway temp file avoids depending on an uncommitted fixture.
        $keyFile = tempnam(sys_get_temp_dir(), 'polydock-test-key-');
        file_put_contents($keyFile, 'not-a-real-key');

        // Pre-seed a fresh token cache file so initLagoonClient() takes the
        // cache branch instead of fetching a real token over SSH (which only
        // works on machines with Lagoon access, not in CI). The file name
        // mirrors the md5 scheme in PolydockServiceProviderFTLagoon.
        $cacheDir = sys_get_temp_dir().'/polydock-test-token-cache-'.getmypid();
        @mkdir($cacheDir, 0777, true);
        $tokenFile = $cacheDir.DIRECTORY_SEPARATOR.md5(
            'ssh.lagoon.amazeeio.cloud-'.$keyFile.'-lagoon-32222-https://api.lagoon.amazeeio.cloud/graphql'
        ).'.token';
        file_put_contents($tokenFile, 'not-a-real-token');

        try {
            // The exact production failure path: constructor -> setLogger()
            // on an uninitialized typed property.
            $provider = new PolydockServiceProviderFTLagoon([
                'ssh_private_key_file' => $keyFile,
                'token_cache_dir' => $cacheDir,
            ], new PolydockLogger);

            $this->assertInstanceOf(PolydockLogger::class, $provider->getLogger());
        } finally {
            @unlink($keyFile);
            @unlink($tokenFile);
            @rmdir($cacheDir);
        }
    }

    public function test_cache_logger_flush_still_works_when_logger_was_initialized(): void
    {
        $subject = new class
        {
            use PolydockAppLoggerTrait;

            protected PolydockAppLoggerInterface $logger;
        };

        $cacheLogger = new PolydockAppCacheLogger;
        $subject->setLogger($cacheLogger);
        $cacheLogger->info('buffered line', ['k' => 'v']);

        $collector = new class implements PolydockAppLoggerInterface
        {
            public array $lines = [];

            public function info(string $message, array $context = []): void
            {
                $this->lines[] = $message;
            }

            public function error(string $message, array $context = []): void {}

            public function warning(string $message, array $context = []): void {}

            public function debug(string $message, array $context = []): void
            {
                $this->lines[] = $message;
            }
        };

        $subject->setLogger($collector);

        // The buffered line was flushed into the replacement logger.
        $this->assertContains('buffered line', $collector->lines);
    }
}
