<?php

declare(strict_types=1);

use App\Polydock\Core\Enums\PolydockAppInstanceStatus;
use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Instances that reached REMOVED before the project-purge feature shipped
 * have purge_eligible_at = NULL, so DispatchProjectPurgeJobsCommand never
 * selects them and their Lagoon projects linger forever. Backfill the grace
 * clock from removed_at (or updated_at for rows predating that column too).
 */
return new class extends Migration
{
    public function up(): void
    {
        $graceDays = (int) config('polydock.cleanup.project_grace_period_days', 14);

        DB::table('polydock_app_instances')
            ->where('status', PolydockAppInstanceStatus::REMOVED->value)
            ->whereNull('removed_at')
            ->update(['removed_at' => DB::raw('updated_at')]);

        DB::table('polydock_app_instances')
            ->where('status', PolydockAppInstanceStatus::REMOVED->value)
            ->whereNull('purge_eligible_at')
            ->orderBy('id')
            ->chunkById(500, function ($rows) use ($graceDays) {
                foreach ($rows as $row) {
                    DB::table('polydock_app_instances')
                        ->where('id', $row->id)
                        ->update([
                            'purge_eligible_at' => Carbon::parse($row->removed_at)->addDays($graceDays),
                        ]);
                }
            });
    }

    public function down(): void
    {
        // Backfill only; nothing sensible to restore.
    }
};
