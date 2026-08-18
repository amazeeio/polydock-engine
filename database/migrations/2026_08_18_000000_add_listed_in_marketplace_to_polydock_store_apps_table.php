<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('polydock_store_apps', function (Blueprint $table): void {
            $table->boolean('listed_in_marketplace')->default(false)->after('available_for_trials');
        });
    }

    public function down(): void
    {
        Schema::table('polydock_store_apps', function (Blueprint $table): void {
            $table->dropColumn('listed_in_marketplace');
        });
    }
};
