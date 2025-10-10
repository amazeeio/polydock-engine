<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('polydock_store_webhooks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('polydock_store_id')->constrained()->cascadeOnDelete();
            $table->string('url');
            $table->boolean('active')->default(true);
            $table->timestamps();

            // Index for faster lookups
            $table->index('active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('polydock_store_webhooks');
    }
};
