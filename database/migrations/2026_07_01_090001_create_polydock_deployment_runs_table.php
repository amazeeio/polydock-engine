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
        Schema::create('polydock_deployment_runs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('polydock_store_app_id')
                ->nullable()
                ->constrained('polydock_store_apps')
                ->nullOnDelete();
            $table->foreignId('triggered_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('trigger_source');
            $table->string('lagoon_bulk_id')->nullable()->index();
            $table->string('status')->default('pending');
            $table->unsignedInteger('total_count')->default(0);
            $table->unsignedInteger('success_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('last_polled_at')->nullable();
            $table->unsignedInteger('poll_attempts')->default(0);
            $table->timestamps();

            $table->index(['status', 'last_polled_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('polydock_deployment_runs');
    }
};
