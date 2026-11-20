<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\PolydockAppInstance;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PolydockAppInstanceSensitiveKeysTest extends TestCase
{
    #[DataProvider('secretBearingKeys')]
    public function test_secret_bearing_data_keys_never_reach_webhooks(string $key): void
    {
        $instance = new PolydockAppInstance;
        $instance->data = [$key => 'dummy-secret', 'lagoon-project-name' => 'demo'];

        foreach ([true, false] as $includeCredentials) {
            $safe = $instance->getWebhookSafeData('data', $includeCredentials);

            self::assertArrayNotHasKey($key, $safe);
            self::assertSame('demo', $safe['lagoon-project-name']);
        }
    }

    /**
     * @return array<string, array{string}>
     */
    public static function secretBearingKeys(): array
    {
        return [
            'generated amazee.ai credentials' => ['amazee-ai-generated-credentials'],
            'claim output (one-time login URL)' => ['claim-command-output'],
            'deploy private key' => ['lagoon-deploy-private-key'],
            'admin api key' => ['app-admin-api-key'],
        ];
    }
}
