<?php

namespace Tests\Feature\Migrations;

use App\Models\PolydockStore;
use App\Models\PolydockStoreApp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MigratePolydockAppClassesTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_up_and_down_maintains_original_namespaces(): void
    {
        // Require the migration file
        $migration = require database_path('migrations/2026_06_23_205000_migrate_polydock_app_classes.php');

        // Ensure we drop the tracking column first if left over
        $migration->down();

        // Create a store first to satisfy any foreign keys if present
        $store = PolydockStore::factory()->create();

        // Let's truncate polydock_store_apps to have a clean test state
        DB::table('polydock_store_apps')->truncate();

        // Create the test store apps using factories to ensure all required fields are correctly populated
        PolydockStoreApp::factory()->create([
            'polydock_store_id' => $store->id,
            'name' => 'App 1',
            'polydock_app_class' => 'App\Polydock\CoreAmazeeioGeneric\PolydockApp',
        ]);

        PolydockStoreApp::factory()->create([
            'polydock_store_id' => $store->id,
            'name' => 'App 2',
            'polydock_app_class' => 'FreedomtechHosting\PolydockAppAmazeeioGeneric\PolydockApp',
        ]);

        PolydockStoreApp::factory()->create([
            'polydock_store_id' => $store->id,
            'name' => 'App 3',
            'polydock_app_class' => 'Amazeeio\PolydockAppAmazeeclaw\PolydockAmazeeClawAiApp',
        ]);

        // Verify pre-migration state
        $this->assertEquals(
            'App\Polydock\CoreAmazeeioGeneric\PolydockApp',
            DB::table('polydock_store_apps')->where('name', 'App 1')->value('polydock_app_class')
        );

        // Run the up migration
        $migration->up();

        // Check if the migrated_from_class column exists
        $this->assertTrue(Schema::hasColumn('polydock_store_apps', 'migrated_from_class'));

        // Check migration updates (collapsed generic app types)
        $this->assertEquals(
            'App\Polydock\Apps\Generic\PolydockApp',
            DB::table('polydock_store_apps')->where('name', 'App 1')->value('polydock_app_class')
        );
        $this->assertEquals(
            'App\Polydock\CoreAmazeeioGeneric\PolydockApp',
            DB::table('polydock_store_apps')->where('name', 'App 1')->value('migrated_from_class')
        );

        $this->assertEquals(
            'App\Polydock\Apps\Generic\PolydockApp',
            DB::table('polydock_store_apps')->where('name', 'App 2')->value('polydock_app_class')
        );
        $this->assertEquals(
            'FreedomtechHosting\PolydockAppAmazeeioGeneric\PolydockApp',
            DB::table('polydock_store_apps')->where('name', 'App 2')->value('migrated_from_class')
        );

        // Run the down migration
        $migration->down();

        // Check column is dropped
        $this->assertFalse(Schema::hasColumn('polydock_store_apps', 'migrated_from_class'));

        // Verify restoration of BOTH distinct original classes without corruption!
        $this->assertEquals(
            'App\Polydock\CoreAmazeeioGeneric\PolydockApp',
            DB::table('polydock_store_apps')->where('name', 'App 1')->value('polydock_app_class')
        );
        $this->assertEquals(
            'FreedomtechHosting\PolydockAppAmazeeioGeneric\PolydockApp',
            DB::table('polydock_store_apps')->where('name', 'App 2')->value('polydock_app_class')
        );
        $this->assertEquals(
            'Amazeeio\PolydockAppAmazeeclaw\PolydockAmazeeClawAiApp',
            DB::table('polydock_store_apps')->where('name', 'App 3')->value('polydock_app_class')
        );
    }
}
