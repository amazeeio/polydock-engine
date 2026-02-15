<?php

namespace Tests\Unit\PolydockEngine\Helpers;

use App\PolydockEngine\Helpers\LagoonHelper;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class LagoonHelperTest extends TestCase
{
    /**
     * NOTE: This private key is a dummy key used strictly for unit testing public key derivation.
     * It does not grant access to any system and is intended for repository-safe testing.
     */
    private string $privateKey = <<<'EOD'
-----BEGIN OPENSSH PRIVATE KEY-----
b3BlbnNzaC1rZXktdjEAAAAABG5vbmUAAAAEbm9uZQAAAAAAAAABAAAAMwAAAAtzc2gtZW
QyNTUxOQAAACABEAdnVD07KXZFDmKkjBMuEl/nvTCAjNa1/otIojBdKAAAAIhV8gY+VfIG
PgAAAAtzc2gtZWQyNTUxOQAAACABEAdnVD07KXZFDmKkjBMuEl/nvTCAjNa1/otIojBdKA
AAAECazOXPG40gXx3YDyJmEohSfK3iwS2HaXw7DKteGORMWQEQB2dUPTspdkUOYqSMEy4S
X+e9MICM1rX+i0iiMF0oAAAAAAECAwQF
-----END OPENSSH PRIVATE KEY-----
EOD;

    // The raw public key string without comment
    private string $publicKey = 'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIAEQB2dUPTspdkUOYqSMEy4SX+e9MICM1rX+i0iiMF0o';

    public function test_get_public_key_from_private_key_returns_correct_public_key()
    {
        $derivedPublicKey = LagoonHelper::getPublicKeyFromPrivateKey($this->privateKey);

        $this->assertStringContainsString($this->publicKey, $derivedPublicKey);
    }

    public function test_get_public_key_from_private_key_returns_null_for_invalid_key()
    {
        Log::shouldReceive('error')->once();

        $invalidKey = 'invalid-key-content';
        $derivedPublicKey = LagoonHelper::getPublicKeyFromPrivateKey($invalidKey);

        $this->assertNull($derivedPublicKey);
    }

    public function test_get_public_key_from_private_key_returns_null_for_empty_key()
    {
        $derivedPublicKey = LagoonHelper::getPublicKeyFromPrivateKey('');

        $this->assertNull($derivedPublicKey);
    }
}
