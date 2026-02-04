<?php

use App\Enums\UserRemoteRegistrationStatusEnum;
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
        Schema::create('user_remote_registrations', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36)->unique();
            $table->string('email');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_group_id')->nullable()->constrained()->nullOnDelete();
            $table->json('request_data');
            $table->json('result_data')->nullable();
            $table->enum('status', UserRemoteRegistrationStatusEnum::getValues())->default(UserRemoteRegistrationStatusEnum::PENDING->value);
            $table->timestamps();

            // Index on email for faster lookups
            $table->index('email');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_remote_registrations');
    }
};
