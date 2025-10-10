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
        Schema::table('polydock_store_apps', function (Blueprint $table) {
            // Post Deploy
            $table->string('lagoon_post_deploy_service')->nullable();
            $table->string('lagoon_post_deploy_container')->nullable();

            // Pre Upgrade
            $table->string('lagoon_pre_upgrade_service')->nullable();
            $table->string('lagoon_pre_upgrade_container')->nullable();

            // Upgrade
            $table->string('lagoon_upgrade_service')->nullable();
            $table->string('lagoon_upgrade_container')->nullable();

            // Post Upgrade
            $table->string('lagoon_post_upgrade_service')->nullable();
            $table->string('lagoon_post_upgrade_container')->nullable();

            // Claim
            $table->string('lagoon_claim_service')->nullable();
            $table->string('lagoon_claim_container')->nullable();

            // Pre Remove
            $table->string('lagoon_pre_remove_service')->nullable();
            $table->string('lagoon_pre_remove_container')->nullable();

            // Remove
            $table->string('lagoon_remove_service')->nullable();
            $table->string('lagoon_remove_container')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('polydock_store_apps', function (Blueprint $table) {
            // Post Deploy
            $table->dropColumn('lagoon_post_deploy_service');
            $table->dropColumn('lagoon_post_deploy_container');

            // Pre Upgrade
            $table->dropColumn('lagoon_pre_upgrade_service');
            $table->dropColumn('lagoon_pre_upgrade_container');

            // Upgrade
            $table->dropColumn('lagoon_upgrade_service');
            $table->dropColumn('lagoon_upgrade_container');

            // Post Upgrade
            $table->dropColumn('lagoon_post_upgrade_service');
            $table->dropColumn('lagoon_post_upgrade_container');

            // Claim
            $table->dropColumn('lagoon_claim_service');
            $table->dropColumn('lagoon_claim_container');

            // Pre Remove
            $table->dropColumn('lagoon_pre_remove_service');
            $table->dropColumn('lagoon_pre_remove_container');

            // Remove
            $table->dropColumn('lagoon_remove_service');
            $table->dropColumn('lagoon_remove_container');
        });
    }
};
