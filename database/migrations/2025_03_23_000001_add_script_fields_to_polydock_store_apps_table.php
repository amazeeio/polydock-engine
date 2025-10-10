<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('polydock_store_apps', function (Blueprint $table) {
            $table->text('lagoon_post_deploy_script')->nullable()->after('lagoon_deploy_branch');
            $table->text('lagoon_pre_upgrade_script')->nullable()->after('lagoon_post_deploy_script');
            $table->text('lagoon_upgrade_script')->nullable()->after('lagoon_pre_upgrade_script');
            $table->text('lagoon_post_upgrade_script')->nullable()->after('lagoon_upgrade_script');
            $table->text('lagoon_claim_script')->nullable()->after('lagoon_post_upgrade_script');
            $table->text('lagoon_pre_remove_script')->nullable()->after('lagoon_claim_script');
            $table->text('lagoon_remove_script')->nullable()->after('lagoon_pre_remove_script');
        });
    }

    public function down(): void
    {
        Schema::table('polydock_store_apps', function (Blueprint $table) {
            $table->dropColumn([
                'lagoon_post_deploy_script',
                'lagoon_pre_upgrade_script',
                'lagoon_upgrade_script',
                'lagoon_post_upgrade_script',
                'lagoon_claim_script',
                'lagoon_pre_remove_script',
                'lagoon_remove_script',
            ]);
        });
    }
};
