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
            $table->boolean('redeploy_enabled')->default(false);
            $table->unsignedInteger('redeploy_interval_days')->nullable();
            $table->unsignedInteger('beta_redeploy_interval_days')->nullable();
        });

        Schema::table('user_groups', function (Blueprint $table) {
            $table->boolean('is_beta')->default(false)->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('polydock_store_apps', function (Blueprint $table) {
            $table->dropColumn([
                'redeploy_enabled',
                'redeploy_interval_days',
                'beta_redeploy_interval_days',
            ]);
        });

        Schema::table('user_groups', function (Blueprint $table) {
            $table->dropIndex(['is_beta']);
            $table->dropColumn('is_beta');
        });
    }
};
