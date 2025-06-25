<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('polydock_stores', function (Blueprint $table) {
            $table->renameColumn('amazee_ai_backend_region_id', 'amazee_ai_backend_region_id_ext');
            $table->renameColumn('lagoon_deploy_organization_id', 'lagoon_deploy_organization_id_ext');
            $table->renameColumn('lagoon_deploy_region_id', 'lagoon_deploy_region_id_ext');
        });
    }

    public function down(): void
    {
        Schema::table('polydock_stores', function (Blueprint $table) {
            $table->renameColumn('amazee_ai_backend_region_id_ext', 'amazee_ai_backend_region_id');
            $table->renameColumn('lagoon_deploy_organization_id_ext', 'lagoon_deploy_organization_id');
            $table->renameColumn('lagoon_deploy_region_id_ext', 'lagoon_deploy_region_id');
        });
    }
}; 