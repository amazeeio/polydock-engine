<?php

namespace App\Console\Commands;

use App\Jobs\EnsureUnallocatedAppInstancesJob;
use App\Models\PolydockStoreApp;
use App\Enums\PolydockStoreAppStatusEnum;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PollEnsureUnallocatedAppInstancesJobCommand extends Command
{
    protected $signature = 'polydock:poll-unallocated-instances';
    protected $description = 'Poll and dispatch jobs to ensure unallocated instances';

    private const LOOP_DURATION = 300; // 5 minutes
    private const SLEEP_DURATION = 5; // 5 seconds

    public function handle(): int
    {
        $endTime = now()->addSeconds(self::LOOP_DURATION);

        Log::info('Starting unallocated instances polling loop', [
            'duration' => self::LOOP_DURATION,
            'sleep' => self::SLEEP_DURATION
        ]);

        while (now()->lt($endTime)) {
            // Get all available apps that need more unallocated instances
            $apps = PolydockStoreApp::query()
                ->where('status', PolydockStoreAppStatusEnum::AVAILABLE)
                ->get()
                ->filter(fn($app) => $app->needs_more_unallocated_instances);

            $neededTotal = 0;
            foreach ($apps as $app) {
                $needed = $app->target_unallocated_app_instances - $app->unallocated_instances_count;
                $neededTotal += $needed;
            }

            if ($neededTotal > 0) {
                Log::info('Found apps needing unallocated instances', [
                    'needed_total' => $neededTotal
                ]);

                EnsureUnallocatedAppInstancesJob::dispatch()
                    ->onQueue('unallocated-instance-creation');

                $this->info("Dispatched job for {$neededTotal} needed instances");
            }

            $this->info("Sleeping for " . self::SLEEP_DURATION . " seconds...");
            sleep(self::SLEEP_DURATION);
        }

        Log::info('Unallocated instances polling loop completed');
        return Command::SUCCESS;
    }
} 