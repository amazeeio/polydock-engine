<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('polydock_app_instance_status_transitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('polydock_app_instance_id')
                ->constrained('polydock_app_instances')
                ->cascadeOnDelete();
            // Null for the row recorded when an instance is created as NEW.
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->timestamp('created_at');

            $table->index(['polydock_app_instance_id', 'created_at'], 'papist_instance_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('polydock_app_instance_status_transitions');
    }
};
