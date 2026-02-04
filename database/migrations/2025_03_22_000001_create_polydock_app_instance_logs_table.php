<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('polydock_app_instance_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('polydock_app_instance_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->string('type')->index();
            $table->string('level');
            $table->text('message');
            $table->json('data')->nullable();
            $table->timestamps();

            // Add indexes for common queries
            $table->index(['polydock_app_instance_id', 'type'], 'pdck_app_inst_log_type_idx');
            $table->index(['polydock_app_instance_id', 'level'], 'pdck_app_inst_log_level_idx');
            $table->index(['polydock_app_instance_id', 'created_at'], 'pdck_app_inst_log_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('polydock_app_instance_logs');
    }
}; 