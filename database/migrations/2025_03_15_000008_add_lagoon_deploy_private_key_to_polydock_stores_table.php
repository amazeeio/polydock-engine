<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('polydock_stores', function (Blueprint $table) {
            $table->text('lagoon_deploy_private_key')->nullable()->default(null)->after('lagoon_deploy_project_prefix');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('polydock_stores', function (Blueprint $table) {
            $table->dropColumn('lagoon_deploy_private_key');
        });
    }
}; 