<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use amazeeio\PolydockApp\Enums\PolydockAppInstanceStatus;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('polydock_app_instances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('polydock_store_app_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_group_id')->constrained()->cascadeOnDelete();
            $table->string('app_type');
            $table->enum('status', array_column(PolydockAppInstanceStatus::cases(), 'value'));
            $table->string('status_message')->nullable();
            $table->json('data')->nullable();
            $table->timestamps();

            // Index for faster lookups
            $table->index('status');
            $table->index('app_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('polydock_app_instances');
    }
};