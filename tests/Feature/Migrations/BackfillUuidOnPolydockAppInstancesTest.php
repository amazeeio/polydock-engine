<?php

declare(strict_types=1);

namespace Tests\Feature\Migrations;

use App\Models\PolydockStore;
use App\Models\PolydockStoreApp;
use App\Polydock\Core\Enums\PolydockAppInstanceStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class BackfillUuidOnPolydockAppInstancesTest extends TestCase
{
    use RefreshDatabase;

    public function test_backfill_assigns_uuids_to_legacy_null_rows_only(): void
    {
        $store = PolydockStore::factory()->create();
        $storeApp = PolydockStoreApp::factory()->create([
            'polydock_store_id' => $store->id,
        ]);

        // Insert directly so no model boot hook fills the uuid, mirroring rows
        // created before the uuid column existed.
        $legacyId = DB::table('polydock_app_instances')->insertGetId([
            'polydock_store_app_id' => $storeApp->id,
            'name' => 'legacy-null-uuid',
            'app_type' => 'test-app',
            'status' => PolydockAppInstanceStatus::NEW->value,
            'uuid' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $existingUuid = '0f1c9a3e-6d2b-4e1a-9b6f-2c4d8e7a5b31';
        $modernId = DB::table('polydock_app_instances')->insertGetId([
            'polydock_store_app_id' => $storeApp->id,
            'name' => 'modern-with-uuid',
            'app_type' => 'test-app',
            'status' => PolydockAppInstanceStatus::NEW->value,
            'uuid' => $existingUuid,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration = require database_path('migrations/2026_08_06_000001_backfill_uuid_on_polydock_app_instances_table.php');
        self::assertInstanceOf(Migration::class, $migration);
        if (! method_exists($migration, 'up')) {
            self::fail('Backfill migration does not define up()');
        }
        $migration->up();

        $legacyUuid = DB::table('polydock_app_instances')->where('id', $legacyId)->value('uuid');
        self::assertNotNull($legacyUuid);
        self::assertTrue(Str::isUuid($legacyUuid));

        // Rows that already had a uuid keep it untouched.
        self::assertSame(
            $existingUuid,
            DB::table('polydock_app_instances')->where('id', $modernId)->value('uuid'),
        );
    }
}
