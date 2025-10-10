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
            $table->string('app_url')->nullable();
            $table->string('app_one_time_login_url')->nullable();
            $table->timestamp('app_one_time_login_valid_until')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('polydock_app_instances', function (Blueprint $table) {
            $table->dropColumn('app_url');
            $table->dropColumn('app_one_time_login_url');
            $table->dropColumn('app_one_time_login_valid_until');
        });
    }
};
