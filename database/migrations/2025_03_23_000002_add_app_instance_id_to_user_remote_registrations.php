<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_remote_registrations', function (Blueprint $table) {
            $table->unsignedBigInteger('polydock_app_instance_id')->nullable()->after('user_group_id');

            $table->foreign('polydock_app_instance_id')
                ->references('id')
                ->on('polydock_app_instances')
                ->cascadeOnDelete();

            // Add an index for faster lookups
            $table->index('polydock_app_instance_id', 'user_remote_reg_app_instance_idx');
        });
    }

    public function down(): void
    {
        Schema::table('user_remote_registrations', function (Blueprint $table) {
            $table->dropForeign(['polydock_app_instance_id']);
            $table->dropIndex('user_remote_reg_app_instance_idx');
            $table->dropColumn('polydock_app_instance_id');
        });
    }
};
