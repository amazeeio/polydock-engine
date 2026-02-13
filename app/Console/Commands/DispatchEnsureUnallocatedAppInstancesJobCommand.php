<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\EnsureUnallocatedAppInstancesJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class DispatchEnsureUnallocatedAppInstancesJobCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'polydock:ensure-unallocated-instances';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dispatch job to ensure apps have their target number of unallocated instances';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Dispatching EnsureUnallocatedAppInstancesJob...');

        Log::info('Dispatching EnsureUnallocatedAppInstancesJob via command');

        try {
            EnsureUnallocatedAppInstancesJob::dispatch()->onQueue('unallocated-instance-creation');

            $this->info('Job dispatched successfully');
            Log::info('EnsureUnallocatedAppInstancesJob dispatched successfully via command');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Failed to dispatch job: '.$e->getMessage());
            Log::error('Failed to dispatch EnsureUnallocatedAppInstancesJob via command', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return Command::FAILURE;
        }
    }
}
