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
        Schema::table('polydock_app_instances', function (Blueprint $table) {
            $table->foreignId('deployment_run_id')
                ->nullable()
                ->after('user_group_id')
                ->constrained('polydock_deployment_runs')
                ->nullOnDelete();
            $table->string('last_deployment_name')->nullable();
            $table->string('last_deployment_status')->nullable();
            $table->timestamp('last_deployed_at')->nullable();
            $table->timestamp('last_deploy_triggered_at')->nullable();
            $table->timestamp('next_redeploy_at')->nullable();

            $table->index('next_redeploy_at');
            $table->index(['polydock_store_app_id', 'next_redeploy_at'], 'app_instances_store_app_next_redeploy_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('polydock_app_instances', function (Blueprint $table) {
            $table->dropIndex('app_instances_store_app_next_redeploy_idx');
            $table->dropIndex(['next_redeploy_at']);
            $table->dropForeign(['deployment_run_id']);
            $table->dropColumn([
                'deployment_run_id',
                'last_deployment_name',
                'last_deployment_status',
                'last_deployed_at',
                'last_deploy_triggered_at',
                'next_redeploy_at',
            ]);
        });
    }
};
