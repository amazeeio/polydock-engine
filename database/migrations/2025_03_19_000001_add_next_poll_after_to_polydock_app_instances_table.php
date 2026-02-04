<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('polydock_app_instances', function (Blueprint $table) {
            $table->timestamp('next_poll_after')->nullable()->after('status_message');
            
            // Add index for efficient polling queries
            $table->index(['status', 'next_poll_after'], 'polydock_app_instances_polling_idx');
        });
    }

    public function down(): void
    {
        Schema::table('polydock_app_instances', function (Blueprint $table) {
            $table->dropIndex('polydock_app_instances_polling_idx');
            $table->dropColumn('next_poll_after');
        });
    }
}; 