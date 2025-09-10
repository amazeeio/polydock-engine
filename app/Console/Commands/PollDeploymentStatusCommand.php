<?php

namespace App\Console\Commands;

use App\Jobs\ProcessPolydockAppInstanceJobs\Deploy\PollDeploymentJob;
use App\Models\PolydockAppInstance;
use amazeeio\PolydockApp\Enums\PolydockAppInstanceStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PollDeploymentStatusCommand extends Command
{
    protected $signature = 'polydock:poll-deployment-status';
    protected $description = 'Poll deployment status for running instances';

    private const LOOP_DURATION = 300; // 5 minutes
    private const SLEEP_DURATION = 5; // 5 seconds
    private const MAX_INSTANCES = 10;

    public function handle(): int
    {
        $endTime = now()->addSeconds(self::LOOP_DURATION);

        Log::info('Starting deployment status polling loop', [
            'duration' => self::LOOP_DURATION,
            'sleep' => self::SLEEP_DURATION,
            'max_instances' => self::MAX_INSTANCES
        ]);

        while (now()->lt($endTime)) {
            $instances = PolydockAppInstance::query()
                ->where('status', PolydockAppInstanceStatus::DEPLOY_RUNNING)
                ->where(function ($query) {
                    $query->whereNull('next_poll_after')
                        ->orWhere('next_poll_after', '<=', now());
                })
                ->limit(self::MAX_INSTANCES)
                ->get();

            $count = $instances->count();
            if ($count > 0) {
                Log::info("Found {$count} instances to poll");

                foreach ($instances as $instance) {
                    Log::info('Dispatching PollDeploymentJob', [
                        'app_instance_id' => $instance->id
                    ]);

                    PollDeploymentJob::dispatch($instance->id)
                        ->onQueue('polydock-app-instance-processing-deploy');
                }
            }

            $this->info("Processed {$count} instances, sleeping for " . self::SLEEP_DURATION . " seconds...");
            sleep(self::SLEEP_DURATION);
        }

        Log::info('Deployment status polling loop completed');
        return Command::SUCCESS;
    }
}