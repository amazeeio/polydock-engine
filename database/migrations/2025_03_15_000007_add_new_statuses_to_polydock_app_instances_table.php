<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use amazeeio\PolydockApp\Enums\PolydockAppInstanceStatus;

return new class extends Migration
{
    public function up(): void
    {
        // SQLite doesn't support MODIFY COLUMN with ENUM
        // In SQLite, enum columns are stored as TEXT, so the constraint is at application level
        if (DB::getDriverName() === 'sqlite') {
            // For SQLite, the enum constraint is handled by Laravel at the application level
            // No schema modification needed since it's already TEXT that can accept 'new'
            return;
        }

        // MySQL/PostgreSQL: Modify the actual ENUM type
        // Get the current enum values, excluding 'new' since we're adding it
        $currentEnumValues = implode("','", array_filter(
            PolydockAppInstanceStatus::getValues(),
            fn($value) => $value !== 'new'
        ));

        // Add NEW to the enum
        DB::statement("ALTER TABLE polydock_app_instances MODIFY COLUMN status ENUM('$currentEnumValues','new')");
    }

    public function down(): void
    {
        // SQLite doesn't support MODIFY COLUMN with ENUM
        if (DB::getDriverName() === 'sqlite') {
            // For SQLite, no schema change needed for rollback
            return;
        }

        // MySQL/PostgreSQL: Rollback the ENUM modification
        $newStatuses = [
            'new',
            'running-healthy',
            'running-unresponsive',
            'running-unhealthy',
            'pending-pre-upgrade',
            'pre-upgrade-running',
            'pre-upgrade-completed',
            'pre-upgrade-failed',
            'pending-upgrade',
            'upgrade-running',
            'upgrade-completed',
            'upgrade-failed',
            'pending-post-upgrade',
            'post-upgrade-running',
            'post-upgrade-completed',
            'post-upgrade-failed',
            'pending-post-upgrade',
            'post-upgrade-running',
            'post-upgrade-completed',
            'post-upgrade-failed',
            'pending-post-upgrade',
            'post-upgrade-running',
            'post-upgrade-completed',
            'post-upgrade-failed',
            'pending-post-upgrade',
            'post-upgrade-running',
            'post-upgrade-completed',
            'post-upgrade-failed',
        ];

        // Get the original enum values (excluding NEW)
        $originalEnumValues = array_filter(
            PolydockAppInstanceStatus::getValues(),
            fn($value) => !in_array($value, $newStatuses)
        );

        $enumString = implode("','", array_merge($originalEnumValues, $newStatuses));

        // Remove NEW from the enum
        DB::statement("ALTER TABLE polydock_app_instances MODIFY COLUMN status ENUM('$enumString')");
    }
};