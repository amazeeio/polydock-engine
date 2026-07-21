<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\PolydockAppInstanceLog;
use Illuminate\Support\Facades\Log;

class PruneInstanceLogsCommand extends BaseCommand
{
    protected $signature = 'polydock:prune-instance-logs
        {--days= : Override the retention window in days}
        {--dry-run : Report what would be deleted without deleting}';

    protected $description = 'Prune operational app-instance log rows older than the retention window';

    public function handle(): int
    {
        $days = max(1, (int) ($this->option('days')
            ?? config('polydock.cleanup.instance_log_retention_days', 7)));

        $cutoff = now()->subDays($days);

        if ($this->option('dry-run')) {
            $count = PolydockAppInstanceLog::where('created_at', '<', $cutoff)->count();
            $this->info("[dry-run] Would delete {$count} instance log row(s) older than {$days} day(s).");

            return self::SUCCESS;
        }

        $total = 0;
        do {
            // Chunked deletes keep the lock/transaction footprint small on a
            // large table. Chunk via plucked primary keys: LIMIT on DELETE is
            // MySQL-only grammar and is silently dropped on other drivers
            // (including the sqlite used in tests), which would turn this
            // into one unbounded DELETE.
            $ids = PolydockAppInstanceLog::where('created_at', '<', $cutoff)
                ->orderBy('id')
                ->limit(10000)
                ->pluck('id');

            if ($ids->isEmpty()) {
                break;
            }

            $total += PolydockAppInstanceLog::whereIn('id', $ids)->delete();
        } while (true);

        Log::info('Pruned operational instance logs', [
            'deleted' => $total,
            'retention_days' => $days,
        ]);
        $this->info("Deleted {$total} instance log row(s) older than {$days} day(s).");

        return self::SUCCESS;
    }
}
