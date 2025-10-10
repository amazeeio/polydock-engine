<?php

namespace Database\Migrations;

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
            $table->string('lagoon_deploy_group_name')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('polydock_stores', function (Blueprint $table) {
            $table->dropColumn('lagoon_deploy_group_name');
        });
    }
};
