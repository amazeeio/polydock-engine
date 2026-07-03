<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\PolydockDeploymentRunStatusEnum;
use App\Jobs\PollDeploymentRunJob;
use App\Models\PolydockDeploymentRun;
use Illuminate\Console\Command;

/**
 * Drives repeated polling of in-flight deployment runs. Each due run gets a
 * PollDeploymentRunJob dispatched; the job performs one poll pass and the run's
 * poll backoff (last_polled_at / poll_attempts) decides when it is due again.
 */
class PollDeploymentRunsCommand extends BaseCommand
{
    protected $signature = 'polydock:deployments:poll';

    protected $description = 'Poll in-flight Lagoon deployment runs for completion';

    public function handle(): int
    {
        $interval = (int) config('polydock.deploy.poll_interval_minutes', 5);
        $maxAttempts = (int) config('polydock.deploy.max_poll_attempts', 144);
        $threshold = now()->subMinutes($interval);

        $runs = PolydockDeploymentRun::query()
            ->whereIn('status', [
                PolydockDeploymentRunStatusEnum::PENDING->value,
                PolydockDeploymentRunStatusEnum::RUNNING->value,
            ])
            ->where('poll_attempts', '<', $maxAttempts)
            ->where(function ($query) use ($threshold) {
                $query->whereNull('last_polled_at')
                    ->orWhere('last_polled_at', '<=', $threshold);
            })
            ->orderBy('last_polled_at')
            ->get();

        foreach ($runs as $run) {
            PollDeploymentRunJob::dispatch($run->id);
        }

        $this->info("Dispatched polling for {$runs->count()} in-flight deployment run(s).");

        return Command::SUCCESS;
    }
}
