<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_remote_registrations', function (Blueprint $table) {
            $table->unsignedBigInteger('polydock_store_app_id')->nullable()->after('user_group_id');
            
            $table->foreign('polydock_store_app_id')
                ->references('id')
                ->on('polydock_store_apps')
                ->cascadeOnDelete();

            // Add an index for faster lookups
            $table->index('polydock_store_app_id', 'user_remote_reg_store_app_idx');
        });
    }

    public function down(): void
    {
        Schema::table('user_remote_registrations', function (Blueprint $table) {
            $table->dropForeign(['polydock_store_app_id']);
            $table->dropIndex('user_remote_reg_store_app_idx');
            $table->dropColumn('polydock_store_app_id');
        });
    }
}; 