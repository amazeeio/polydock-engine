<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\PolydockAppInstance;
use FreedomtechHosting\PolydockApp\Enums\PolydockAppInstanceStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Picks REMOVED app instances whose grace period has elapsed (or which have
 * been flagged for force purge) and transitions them to PENDING_PURGE so the
 * ProcessProjectPurgeJob can attempt full Lagoon project deletion.
 *
 * Designed to run on a short cron (every 10 minutes by default). Backoff
 * between attempts is enforced via purge_last_attempted_at.
 */
class DispatchProjectPurgeJobsCommand extends Command
{
    protected $signature = 'polydock:dispatch-project-purge
                          {--dry-run : List eligible instances without dispatching}
                          {--limit= : Override the per-run limit}';

    protected $description = 'Dispatch full Lagoon project purge jobs for REMOVED instances past their grace period';

    public function handle(): int
    {
        $isDryRun = (bool) $this->option('dry-run');
        $limit = (int) ($this->option('limit') ?? config('polydock.cleanup.purge_max_per_run', 25));
        $pollInterval = (int) config('polydock.cleanup.purge_poll_interval_minutes', 10);
        $maxAttempts = (int) config('polydock.cleanup.purge_max_poll_attempts', 144);

        $now = now();
        $backoffCutoff = $now->copy()->subMinutes($pollInterval);

        // First, push instances that have exceeded the polling cap to PURGE_FAILED
        // so they stop being re-dispatched (admin must explicitly retry).
        $stuck = PolydockAppInstance::query()
            ->where('status', PolydockAppInstanceStatus::REMOVED)
            ->where('purge_attempts', '>=', $maxAttempts)
            ->get();

        foreach ($stuck as $instance) {
            $message = sprintf(
                'Purge polling cap reached (%d attempts) without success',
                $instance->purge_attempts,
            );

            $this->warn(sprintf('[stuck] %s (id=%d): %s', $instance->name, $instance->id, $message));
            Log::warning('Marking instance PURGE_FAILED after polling cap', [
                'app_instance_id' => $instance->id,
                'attempts' => $instance->purge_attempts,
            ]);

            if (! $isDryRun) {
                $instance->purge_failure_reason = $message;
                $instance->setStatus(PolydockAppInstanceStatus::PURGE_FAILED, $message);
                $instance->save();
            }
        }

        // Now find eligible candidates.
        $candidates = PolydockAppInstance::query()
            ->where('status', PolydockAppInstanceStatus::REMOVED)
            ->where('purge_attempts', '<', $maxAttempts)
            ->where(function ($query) use ($now) {
                // Either grace period elapsed naturally...
                $query->where(function ($q) use ($now) {
                    $q->whereNotNull('purge_eligible_at')
                        ->where('purge_eligible_at', '<=', $now);
                })
                    // ...or admin-forced.
                    ->orWhereNotNull('force_purge_requested_at');
            })
            ->where(function ($query) use ($backoffCutoff) {
                $query->whereNull('purge_last_attempted_at')
                    ->orWhere('purge_last_attempted_at', '<=', $backoffCutoff);
            })
            ->orderBy('purge_eligible_at')
            ->limit($limit)
            ->get();

        if ($candidates->isEmpty()) {
            $this->info('No instances eligible for project purge.');

            return self::SUCCESS;
        }

        $this->info(sprintf('Found %d instance(s) eligible for project purge.', $candidates->count()));

        foreach ($candidates as $instance) {
            $reason = $instance->force_purge_requested_at !== null ? 'forced' : 'grace-period elapsed';
            $line = sprintf(
                ' - %s (id=%d, attempts=%d, %s)',
                $instance->name,
                $instance->id,
                $instance->purge_attempts ?? 0,
                $reason,
            );

            if ($isDryRun) {
                $this->line('[dry-run]'.$line);

                continue;
            }

            $this->line($line);

            Log::info('Dispatching ProcessProjectPurgeJob', [
                'app_instance_id' => $instance->id,
                'reason' => $reason,
                'attempts' => $instance->purge_attempts ?? 0,
            ]);

            // Setting status triggers the listener which dispatches the job.
            $instance->setStatus(PolydockAppInstanceStatus::PENDING_PURGE, 'Project purge dispatched ('.$reason.')');
            $instance->save();
        }

        return self::SUCCESS;
    }
}
