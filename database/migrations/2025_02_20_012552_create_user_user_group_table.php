<?php

use App\Enums\UserGroupRoleEnum;
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
        Schema::create('user_user_group', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_group_id')->constrained()->cascadeOnDelete();
            $table->enum('role', UserGroupRoleEnum::getValues());

            $table->timestamps();

            $table->unique(['user_id', 'user_group_id']);

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_user_group');
    }
};
