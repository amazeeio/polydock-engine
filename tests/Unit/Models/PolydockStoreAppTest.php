<?php

namespace Tests\Unit\Models;

use App\Models\PolydockStore;
use App\Models\PolydockStoreApp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PolydockStoreAppTest extends TestCase
{
    use RefreshDatabase;

    public function test_private_key_is_hidden_from_serialization(): void
    {
        $store = PolydockStore::factory()->create();
        $store->setPolydockVariableValue('lagoon_deploy_private_key', 'secret-deploy-key', true);

        $app = PolydockStoreApp::factory()->create([
            'polydock_store_id' => $store->id,
        ]);

        $this->assertEquals('secret-deploy-key', $app->lagoon_deploy_private_key);

        $array = $app->toArray();
        $json = json_encode($app);

        $this->assertArrayNotHasKey('lagoon_deploy_private_key', $array);
        $this->assertStringNotContainsString('lagoon_deploy_private_key', $json);
        $this->assertStringNotContainsString('secret-deploy-key', $json);
    }
}
