<?php

namespace Tests\Unit\Models;

use App\Models\PolydockStore;
use App\Models\PolydockVariable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class PolydockStoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_set_and_get_lagoon_deploy_private_key()
    {
        $store = PolydockStore::factory()->create();
        $privateKey = 'test-private-key';

        $store->setPolydockVariableValue('lagoon_deploy_private_key', $privateKey, true);

        $this->assertEquals($privateKey, $store->lagoon_deploy_private_key);
    }

    public function test_lagoon_deploy_private_key_is_encrypted_in_database()
    {
        $store = PolydockStore::factory()->create();
        $privateKey = 'test-private-key';

        $store->setPolydockVariableValue('lagoon_deploy_private_key', $privateKey, true);

        $variable = PolydockVariable::where('variabled_type', PolydockStore::class)
            ->where('variabled_id', $store->id)
            ->where('name', 'lagoon_deploy_private_key')
            ->first();

        $this->assertNotNull($variable);
        $this->assertTrue($variable->is_encrypted);
        $this->assertNotEquals($privateKey, $variable->value);
        $this->assertEquals($privateKey, Crypt::decryptString($variable->value));
    }

    public function test_lagoon_deploy_private_key_returns_null_when_not_set()
    {
        $store = PolydockStore::factory()->create();

        $this->assertNull($store->lagoon_deploy_private_key);
    }
}
