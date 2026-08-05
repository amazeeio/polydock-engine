<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Capture the newest pre-existing row before adding the column, so a
        // webhook created (defaulting to false) while this migration runs is
        // not flipped to true by the backfill.
        $lastExistingWebhookId = DB::table('polydock_store_webhooks')->max('id');

        Schema::table('polydock_store_webhooks', function (Blueprint $table) {
            $table->boolean('include_sensitive_data')->default(false)->after('secret');
        });

        // Existing webhooks (trial email consumers) rely on receiving generated
        // credentials and raw registration data — keep their behavior unchanged.
        // New webhooks must opt in explicitly.
        if ($lastExistingWebhookId !== null) {
            DB::table('polydock_store_webhooks')
                ->where('id', '<=', $lastExistingWebhookId)
                ->update(['include_sensitive_data' => true]);
        }
    }

    public function down(): void
    {
        Schema::table('polydock_store_webhooks', function (Blueprint $table) {
            $table->dropColumn('include_sensitive_data');
        });
    }
};
