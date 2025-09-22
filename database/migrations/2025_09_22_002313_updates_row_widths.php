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
        Schema::table('polydock_app_instances', function(Blueprint $table) {
            $table->string('name', 1024)->nullable()->change();
            $table->string('app_one_time_login_url', 1024)->nullable()->change();
            $table->string('app_url', 1024)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No down migration as we don't want to reduce column sizes
    }
};
