<?php

namespace Tests\Feature\Api;

use App\Enums\PolydockStoreAppStatusEnum;
use App\Enums\PolydockStoreStatusEnum;
use App\Models\PolydockStore;
use App\Models\PolydockStoreApp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegionsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_get_public_regions_with_available_apps()
    {
        // Create a public store with marketplace listing
        $publicStore = PolydockStore::factory()->create([
            'name' => 'Test Public Region',
            'status' => PolydockStoreStatusEnum::PUBLIC,
            'listed_in_marketplace' => true,
        ]);

        // Create an available app in the public store
        $availableApp = PolydockStoreApp::factory()->create([
            'polydock_store_id' => $publicStore->id,
            'name' => 'Test Available App',
            'status' => PolydockStoreAppStatusEnum::AVAILABLE,
        ]);

        // Create an unavailable app (should not appear in results)
        PolydockStoreApp::factory()->create([
            'polydock_store_id' => $publicStore->id,
            'name' => 'Test Unavailable App',
            'status' => PolydockStoreAppStatusEnum::UNAVAILABLE,
        ]);

        $response = $this->getJson('/api/regions');

        $response
            ->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'regions' => [
                        '*' => [
                            'uuid',
                            'id',
                            'label',
                            'apps' => [
                                '*' => [
                                    'uuid',
                                    'label',
                                ],
                            ],
                        ],
                    ],
                ],
                'status_code',
            ])
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'regions' => [
                        [
                            'id' => $publicStore->id,
                            'label' => 'Test Public Region',
                            'apps' => [
                                [
                                    'uuid' => $availableApp->uuid,
                                    'label' => 'Test Available App',
                                ],
                            ],
                        ],
                    ],
                ],
            ]);

        // Verify only one app is returned (the available one)
        $responseData = $response->json();
        $this->assertCount(1, $responseData['data']['regions'][0]['apps']);
    }

    public function test_private_stores_are_not_included()
    {
        // Create a private store
        $privateStore = PolydockStore::factory()->create([
            'name' => 'Private Store',
            'status' => PolydockStoreStatusEnum::PRIVATE,
            'listed_in_marketplace' => true,
        ]);

        PolydockStoreApp::factory()->create([
            'polydock_store_id' => $privateStore->id,
            'status' => PolydockStoreAppStatusEnum::AVAILABLE,
        ]);

        $response = $this->getJson('/api/regions');

        $response->assertStatus(200);

        $responseData = $response->json();
        $this->assertCount(0, $responseData['data']['regions']);
    }

    public function test_stores_not_listed_in_marketplace_are_not_included()
    {
        // Create a public store not listed in marketplace
        $store = PolydockStore::factory()->create([
            'name' => 'Unlisted Store',
            'status' => PolydockStoreStatusEnum::PUBLIC,
            'listed_in_marketplace' => false,
        ]);

        PolydockStoreApp::factory()->create([
            'polydock_store_id' => $store->id,
            'status' => PolydockStoreAppStatusEnum::AVAILABLE,
        ]);

        $response = $this->getJson('/api/regions');

        $response->assertStatus(200);

        $responseData = $response->json();
        $this->assertCount(0, $responseData['data']['regions']);
    }

    public function test_unavailable_stores_are_not_included()
    {
        // Create an unavailable store
        $store = PolydockStore::factory()->create([
            'name' => 'Unavailable Store',
            'status' => PolydockStoreStatusEnum::UNAVAILABLE,
            'listed_in_marketplace' => true,
        ]);

        PolydockStoreApp::factory()->create([
            'polydock_store_id' => $store->id,
            'status' => PolydockStoreAppStatusEnum::AVAILABLE,
        ]);

        $response = $this->getJson('/api/regions');

        $response->assertStatus(200);

        $responseData = $response->json();
        $this->assertCount(0, $responseData['data']['regions']);
    }

    public function test_empty_regions_are_still_included()
    {
        // Create a public store with no apps
        $store = PolydockStore::factory()->create([
            'name' => 'Empty Region',
            'status' => PolydockStoreStatusEnum::PUBLIC,
            'listed_in_marketplace' => true,
        ]);

        $response = $this->getJson('/api/regions');

        $response
            ->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'regions' => [
                        [
                            'id' => $store->id,
                            'label' => 'Empty Region',
                            'apps' => [],
                        ],
                    ],
                ],
            ]);
    }
}
