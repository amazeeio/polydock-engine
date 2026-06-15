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
        Schema::create('polydock_product_types', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::table('polydock_store_apps', function (Blueprint $table) {
            $table->foreignId('polydock_product_type_id')
                ->nullable()
                ->after('polydock_store_id')
                ->constrained('polydock_product_types')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('polydock_store_apps', function (Blueprint $table) {
            $table->dropForeign(['polydock_product_type_id']);
            $table->dropColumn('polydock_product_type_id');
        });

        Schema::dropIfExists('polydock_product_types');
    }
};
