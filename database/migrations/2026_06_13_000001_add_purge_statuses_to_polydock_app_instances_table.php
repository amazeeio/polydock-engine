<?php

use App\Polydock\Core\Enums\PolydockAppInstanceStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        $statuses = implode("','", PolydockAppInstanceStatus::getValues());
        DB::statement("ALTER TABLE polydock_app_instances MODIFY COLUMN status ENUM('$statuses') NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        // To rollback, we exclude the purge statuses: pending-purge, purge-running, purge-failed
        $statusesToExclude = [
            PolydockAppInstanceStatus::PENDING_PURGE->value,
            PolydockAppInstanceStatus::PURGE_RUNNING->value,
            PolydockAppInstanceStatus::PURGE_FAILED->value,
        ];
        $originalStatuses = array_filter(
            PolydockAppInstanceStatus::getValues(),
            fn ($value) => ! in_array($value, $statusesToExclude)
        );

        $statuses = implode("','", $originalStatuses);
        DB::statement("ALTER TABLE polydock_app_instances MODIFY COLUMN status ENUM('$statuses') NOT NULL");
    }
};
