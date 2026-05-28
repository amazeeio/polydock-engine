<?php

use FreedomtechHosting\PolydockApp\Enums\PolydockAppInstanceStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('polydock_app_instances', function (Blueprint $table) {
            $table->timestamp('removed_at')->nullable()->after('trial_complete_email_sent');
            $table->timestamp('purge_eligible_at')->nullable()->after('removed_at')->index();
            $table->timestamp('force_purge_requested_at')->nullable()->after('purge_eligible_at');
            $table->unsignedInteger('purge_attempts')->default(0)->after('force_purge_requested_at');
            $table->timestamp('purge_last_attempted_at')->nullable()->after('purge_attempts');
            $table->text('purge_failure_reason')->nullable()->after('purge_last_attempted_at');
            $table->softDeletes()->after('purge_failure_reason');
        });

        // Backfill existing REMOVED rows so the new dispatcher can operate on them.
        // Done in PHP for portability between MySQL/SQLite/Postgres.
        $graceDays = (int) config('polydock.cleanup.project_grace_period_days', 14);

        DB::table('polydock_app_instances')
            ->where('status', PolydockAppInstanceStatus::REMOVED->value)
            ->whereNull('removed_at')
            ->orderBy('id')
            ->chunkById(500, function ($rows) use ($graceDays) {
                foreach ($rows as $row) {
                    $updatedAt = $row->updated_at ? Carbon::parse($row->updated_at) : Carbon::now();

                    DB::table('polydock_app_instances')
                        ->where('id', $row->id)
                        ->update([
                            'removed_at' => $updatedAt,
                            'purge_eligible_at' => $updatedAt->copy()->addDays($graceDays),
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('polydock_app_instances', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn([
                'removed_at',
                'purge_eligible_at',
                'force_purge_requested_at',
                'purge_attempts',
                'purge_last_attempted_at',
                'purge_failure_reason',
            ]);
        });
    }
};
