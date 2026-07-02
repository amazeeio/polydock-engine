<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('polydock_store_webhooks', function (Blueprint $table) {
            $table->string('secret')->nullable()->after('url');
        });

        // Backfill existing rows: without a secret, signPayload() would sign with
        // an empty key, producing a forgeable "sha256=" signature. Mint one per row.
        foreach (DB::table('polydock_store_webhooks')->whereNull('secret')->pluck('id') as $id) {
            DB::table('polydock_store_webhooks')->where('id', $id)->update(['secret' => Str::random(40)]);
        }
    }

    public function down(): void
    {
        Schema::table('polydock_store_webhooks', function (Blueprint $table) {
            $table->dropColumn('secret');
        });
    }
};
