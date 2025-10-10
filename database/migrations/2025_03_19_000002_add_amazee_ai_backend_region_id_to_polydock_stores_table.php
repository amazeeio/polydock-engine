<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('polydock_stores', function (Blueprint $table) {
            $table->unsignedInteger('amazee_ai_backend_region_id')->nullable()->after('lagoon_deploy_private_key');

            // Add index for efficient lookups
            $table->index('amazee_ai_backend_region_id', 'polydock_stores_amazee_ai_region_idx');
        });
    }

    public function down(): void
    {
        Schema::table('polydock_stores', function (Blueprint $table) {
            $table->dropIndex('polydock_stores_amazee_ai_region_idx');
            $table->dropColumn('amazee_ai_backend_region_id');
        });
    }
};
