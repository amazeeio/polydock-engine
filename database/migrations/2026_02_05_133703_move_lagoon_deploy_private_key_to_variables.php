<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Migrate existing keys to variables
        $stores = DB::table('polydock_stores')->get();

        foreach ($stores as $store) {
            if (! empty($store->lagoon_deploy_private_key)) {
                DB::table('polydock_variables')->insert([
                    'variabled_type' => 'App\Models\PolydockStore',
                    'variabled_id' => $store->id,
                    'name' => 'lagoon_deploy_private_key',
                    'value' => Crypt::encryptString($store->lagoon_deploy_private_key),
                    'is_encrypted' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        Schema::table('polydock_stores', function (Blueprint $table) {
            $table->dropColumn('lagoon_deploy_private_key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('polydock_stores', function (Blueprint $table) {
            $table->text('lagoon_deploy_private_key')->nullable()->after('lagoon_deploy_project_prefix');
        });

        // Restore keys from variables
        $variables = DB::table('polydock_variables')
            ->where('variabled_type', 'App\Models\PolydockStore')
            ->where('name', 'lagoon_deploy_private_key')
            ->get();

        foreach ($variables as $variable) {
            try {
                $value = $variable->is_encrypted
                    ? Crypt::decryptString($variable->value)
                    : $variable->value;

                DB::table('polydock_stores')
                    ->where('id', $variable->variabled_id)
                    ->update(['lagoon_deploy_private_key' => $value]);
            } catch (\Exception $e) {
                // Handle decryption failure or other issues if necessary
                continue;
            }
        }

        // Clean up variables
        DB::table('polydock_variables')
            ->where('variabled_type', 'App\Models\PolydockStore')
            ->where('name', 'lagoon_deploy_private_key')
            ->delete();
    }
};
