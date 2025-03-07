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
            $table->foreignId('user_group_id')->nullable()->constrained()->nullOnDelete();
        });

        Schema::table('polydock_store_apps', function (Blueprint $table) {
            $table->integer('target_unallocated_app_instances')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('polydock_app_instances', function (Blueprint $table) {
            $table->foreignId('user_group_id')->constrained()->cascadeOnDelete();
        });

        Schema::table('polydock_store_apps', function (Blueprint $table) {
            $table->dropColumn('target_unallocated_app_instances');
        });
    }
};
