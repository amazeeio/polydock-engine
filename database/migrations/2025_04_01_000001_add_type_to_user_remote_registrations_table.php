<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Enums\UserRemoteRegistrationType;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_remote_registrations', function (Blueprint $table) {
            $table->enum('type', UserRemoteRegistrationType::getValues())
                ->nullable()
                ->after('uuid');
        });
    }

    public function down(): void
    {
        Schema::table('user_remote_registrations', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
}; 