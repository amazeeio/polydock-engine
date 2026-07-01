<?php

namespace Tests\Unit\Models;

use App\Events\PolydockAppInstanceCreatedWithNewStatus;
use App\Events\PolydockAppInstanceStatusChanged;
use App\Models\PolydockAppInstance;
use App\Models\PolydockStore;
use App\Models\PolydockStoreApp;
use App\Models\UserGroup;
use App\Polydock\Apps\Generic\PolydockAiApp;
use App\Polydock\Core\Enums\PolydockAppInstanceStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * POC for plan 009: transparent encryption-at-rest of the `secret` subtree.
 *
 * Proves that a secret written via storeKeyValue() reads back identically via
 * getKeyValue() (transparent round-trip), while the raw `data` column bytes are
 * ciphertext rather than the plaintext credentials.
 */
class PolydockAppInstanceSecretEncryptionTest extends TestCase
{
    use RefreshDatabase;

    private PolydockStoreApp $storeApp;

    private UserGroup $group;

    protected function setUp(): void
    {
        parent::setUp();

        $store = PolydockStore::factory()->create();
        $this->storeApp = PolydockStoreApp::factory()->create([
            'polydock_store_id' => $store->id,
        ]);
        $this->group = UserGroup::factory()->create();

        Event::fake([
            PolydockAppInstanceCreatedWithNewStatus::class,
            PolydockAppInstanceStatusChanged::class,
        ]);
    }

    private function newInstance(): PolydockAppInstance
    {
        $instance = new PolydockAppInstance;
        $instance->polydock_store_app_id = $this->storeApp->id;
        $instance->user_group_id = $this->group->id;
        $instance->name = 'test-secret-encryption';
        $instance->app_type = PolydockAiApp::class;
        $instance->status = PolydockAppInstanceStatus::NEW;
        $instance->save();

        return $instance;
    }

    public function test_secret_round_trips_transparently_through_key_value_seam(): void
    {
        $secret = [
            'ai' => ['api_key' => 'sk-super-secret-123', 'llm_url' => 'https://llm.example'],
            'vector' => ['db_pass' => 'p@ssw0rd!', 'db_user' => 'vector'],
        ];

        $instance = $this->newInstance();
        $instance->storeKeyValue('secret', $secret);

        // Callers read the plaintext array back unchanged (same object).
        $this->assertSame($secret, $instance->getKeyValue('secret'));

        // And it survives a fresh load from the database.
        $reloaded = PolydockAppInstance::findOrFail($instance->id);
        $this->assertSame($secret, $reloaded->getKeyValue('secret'));
    }

    public function test_raw_db_column_stores_ciphertext_not_plaintext(): void
    {
        $secret = [
            'ai' => ['api_key' => 'sk-super-secret-123'],
            'vector' => ['db_pass' => 'p@ssw0rd!'],
        ];

        $instance = $this->newInstance();
        $instance->storeKeyValue('secret', $secret);

        // Query the raw column bytes directly (bypassing the model accessors).
        $raw = (string) $instance->fresh()->getRawOriginal('data');

        // The plaintext secret material must NOT appear anywhere in the column.
        $this->assertStringNotContainsString('sk-super-secret-123', $raw);
        $this->assertStringNotContainsString('p@ssw0rd!', $raw);

        // The stored secret value is a prefixed ciphertext string.
        $stored = json_decode($raw, true);
        $this->assertIsString($stored['secret']);
        $this->assertStringStartsWith('enc:v1:', $stored['secret']);
    }

    public function test_encryption_is_idempotent_and_not_double_applied(): void
    {
        $secret = ['ai' => ['api_key' => 'sk-abc']];

        $instance = $this->newInstance();
        $instance->storeKeyValue('secret', $secret);

        // Simulate a re-store of the already-decrypted value; must still decrypt cleanly.
        $instance->storeKeyValue('secret', $instance->getKeyValue('secret'));
        $this->assertSame($secret, $instance->fresh()->getKeyValue('secret'));

        // Ciphertext should not be the prefix stacked twice.
        $secondCipher = json_decode((string) $instance->fresh()->getRawOriginal('data'), true)['secret'];
        $this->assertStringStartsWith('enc:v1:', $secondCipher);
        $this->assertStringNotContainsString('enc:v1:enc:v1:', $secondCipher);
    }

    public function test_legacy_plaintext_secret_is_still_readable(): void
    {
        // Simulate a row written before encryption existed: plaintext under `data.secret`.
        $instance = $this->newInstance();
        $instance->data = ['secret' => ['ai' => ['api_key' => 'legacy-plain']]];
        $instance->save();

        // getKeyValue() returns the plaintext untouched (no prefix => passthrough).
        $this->assertSame(['ai' => ['api_key' => 'legacy-plain']], $instance->fresh()->getKeyValue('secret'));
    }

    public function test_corrupted_ciphertext_returns_null_instead_of_throwing(): void
    {
        $instance = $this->newInstance();
        $instance->storeKeyValue('secret', ['ai' => ['api_key' => 'sk-xyz']]);

        // Corrupt the stored ciphertext while keeping the enc:v1: prefix (simulates
        // an APP_KEY rotation without re-encrypt, or a corrupted column value).
        $data = $instance->fresh()->data;
        $data['secret'] = 'enc:v1:not-valid-ciphertext';
        $instance->data = $data;
        $instance->save();

        // The read must fail safe (null) and not throw a DecryptException.
        $this->assertNull($instance->fresh()->getKeyValue('secret'));
    }
}
