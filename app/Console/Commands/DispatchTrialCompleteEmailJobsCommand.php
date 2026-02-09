<?php

namespace App\Console\Commands;

use App\Jobs\ProcessPolydockAppInstanceJobs\Trial\ProcessTrialCompleteEmailJob;
use App\Models\PolydockAppInstance;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class DispatchTrialCompleteEmailJobsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'polydock:dispatch-trial-complete-emails';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Find and dispatch trial complete email jobs for eligible app instances';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $maxPerRun = config('polydock.max_per_run_dispatch_trial_complete_emails');
        $this->info('Finding eligible app instances for trial complete emails...');
        Log::info('Finding eligible app instances for trial complete emails...');

        // Find eligible instances
        $eligibleInstances = PolydockAppInstance::query()
            ->with(['storeApp', 'userGroup.owners']) // Eager load relationships
            ->where('is_trial', true)
            ->whereHas('storeApp', function ($query) {
                $query->where('send_trial_complete_email', true);
            })
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '<=', now())
            ->where('trial_complete_email_sent', false)
            ->limit($maxPerRun)
            ->get();

        if ($eligibleInstances->isEmpty()) {
            $this->info('No eligible app instances found for trial complete emails.');
            Log::info('No eligible app instances found for trial complete emails.');

            return;
        }

        $this->info(sprintf('Found %d eligible app instances. Dispatching jobs...', $eligibleInstances->count()));
        Log::info(sprintf('Found %d eligible app instances. Dispatching jobs...', $eligibleInstances->count()));

        // Dispatch jobs for each eligible instance
        foreach ($eligibleInstances as $instance) {
            $this->info(sprintf(
                'Dispatching trial complete email job for app instance %s (%s)',
                $instance->name,
                $instance->uuid,
            ));
            Log::info(sprintf(
                'Dispatching trial complete email job for app instance %s (%s)',
                $instance->name,
                $instance->uuid,
            ));
            ProcessTrialCompleteEmailJob::dispatch($instance->id);
        }

        $this->info('All jobs dispatched successfully.');
        Log::info('All jobs dispatched successfully.');
    }
}
