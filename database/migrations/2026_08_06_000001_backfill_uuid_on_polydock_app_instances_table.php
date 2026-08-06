<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * The uuid column was added nullable (2025_03_21) without backfilling rows
     * that already existed, so their status transitions would emit webhook
     * payloads with a null app_instance_uuid — the field consumers are told to
     * key on. Assign uuids to those legacy rows, including soft-deleted ones.
     */
    public function up(): void
    {
        DB::table('polydock_app_instances')
            ->whereNull('uuid')
            ->orderBy('id')
            ->chunkById(100, function ($instances): void {
                foreach ($instances as $instance) {
                    DB::table('polydock_app_instances')
                        ->where('id', $instance->id)
                        ->update(['uuid' => Str::uuid()->toString()]);
                }
            });
    }

    public function down(): void
    {
        // Intentionally a no-op: backfilled uuids are indistinguishable from
        // boot-assigned ones, and consumers may already hold them.
    }
};
