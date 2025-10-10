<?php

namespace App\Console\Commands;

use App\Jobs\ProcessPolydockAppInstanceJobs\Trial\ProcessOneDayLeftEmailJob;
use App\Models\PolydockAppInstance;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class DispatchOneDayLeftEmailJobsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'polydock:dispatch-one-day-left-emails';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Find and dispatch one day left email jobs for eligible app instances';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $maxPerRun = config('polydock.max_per_run_dispatch_one_day_left_emails');
        $this->info('Finding eligible app instances for one day left emails...');
        Log::info('Finding eligible app instances for one day left emails...');

        // Find eligible instances
        $eligibleInstances = PolydockAppInstance::query()
            ->with(['storeApp', 'userGroup.owners']) // Eager load relationships
            ->where('is_trial', true)
            ->whereHas('storeApp', function ($query) {
                $query->where('send_one_day_left_email', true);
            })
            ->whereNotNull('send_one_day_left_email_at')
            ->where('send_one_day_left_email_at', '<=', now())
            ->where('one_day_left_email_sent', false)
            ->limit($maxPerRun)
            ->get();

        if ($eligibleInstances->isEmpty()) {
            $this->info('No eligible app instances found for one day left emails.');
            Log::info('No eligible app instances found for one day left emails.');

            return;
        }

        $this->info(sprintf('Found %d eligible app instances. Dispatching jobs...', $eligibleInstances->count()));
        Log::info(sprintf('Found %d eligible app instances. Dispatching jobs...', $eligibleInstances->count()));

        // Dispatch jobs for each eligible instance
        foreach ($eligibleInstances as $instance) {
            $this->info(sprintf('Dispatching one day left email job for app instance %s (%s)', $instance->name, $instance->uuid));
            Log::info(sprintf('Dispatching one day left email job for app instance %s (%s)', $instance->name, $instance->uuid));
            ProcessOneDayLeftEmailJob::dispatch($instance->id);
        }

        $this->info('All jobs dispatched successfully.');
        Log::info('All jobs dispatched successfully.');
    }
}
