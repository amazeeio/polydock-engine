<?php

namespace App\Console\Commands;

use App\Jobs\ProcessPolydockAppInstanceJobs\Trial\ProcessTrialCompleteStageRemovalJob;
use App\Models\PolydockAppInstance;
use FreedomtechHosting\PolydockApp\Enums\PolydockAppInstanceStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class DispatchTrialCompleteStageRemovalJobsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'polydock:dispatch-trial-complete-stage-removal';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Find and dispatch trial complete stage removal jobs for eligible app instances';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $maxPerRun = config('polydock.max_per_run_dispatch_trial_complete_stage_removal');
        $this->info('Finding eligible app instances for trial complete stage removal...');
        Log::info('Finding eligible app instances for trial complete stage removal...');

        // Find eligible instances
        $eligibleInstances = PolydockAppInstance::query()
            ->where('is_trial', true)
            ->where('status', PolydockAppInstanceStatus::RUNNING_HEALTHY_CLAIMED)
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '<=', now())
            ->limit($maxPerRun)
            ->get();

        if ($eligibleInstances->isEmpty()) {
            $this->info('No eligible app instances found for trial complete stage removal.');
            Log::info('No eligible app instances found for trial complete stage removal.');

            return;
        }

        $this->info(sprintf('Found %d eligible app instances. Dispatching jobs...', $eligibleInstances->count()));
        Log::info(sprintf('Found %d eligible app instances. Dispatching jobs...', $eligibleInstances->count()));

        // Dispatch jobs for each eligible instance
        foreach ($eligibleInstances as $instance) {
            $this->info(sprintf(
                'Dispatching trial complete stage removal job for app instance %s (%s)',
                $instance->name,
                $instance->uuid,
            ));
            Log::info(sprintf(
                'Dispatching trial complete stage removal job for app instance %s (%s)',
                $instance->name,
                $instance->uuid,
            ));
            ProcessTrialCompleteStageRemovalJob::dispatch($instance->id);
        }

        $this->info('All jobs dispatched successfully.');
        Log::info('All jobs dispatched successfully.');
    }
}
