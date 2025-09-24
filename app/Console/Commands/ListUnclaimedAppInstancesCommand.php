<?php

namespace App\Console\Commands;

use App\Models\PolydockAppInstance;
use FreedomtechHosting\PolydockApp\Enums\PolydockAppInstanceStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ListUnclaimedAppInstancesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'polydock:list-unclaimed-instances';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List all unclaimed PolydockAppInstances that are older than 14 days';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Searching for unclaimed PolydockAppInstances older than 14 days...');
        
        Log::info('Listing unclaimed PolydockAppInstances via command');

        try {
            // Find all unclaimed instances older than 14 days
            $unclaimedInstances = PolydockAppInstance::where('status', PolydockAppInstanceStatus::RUNNING_HEALTHY_UNCLAIMED)
                ->whereDate('created_at', '<=', now()->subDay(14))
                ->get();

            $count = $unclaimedInstances->count();
            
            if ($count === 0) {
                $this->info('No unclaimed instances found that are older than 14 days.');
                Log::info('No unclaimed instances found older than 14 days');
                return Command::SUCCESS;
            }

            $this->info("Found {$count} unclaimed instances:");
            $this->newLine();

            // Display the instances in a table format
            $headers = ['ID', 'Name', 'Status', 'Created At', 'App Type'];
            $rows = [];

            foreach ($unclaimedInstances as $instance) {
                $rows[] = [
                    $instance->id,
                    $instance->name,
                    $instance->status->value,
                    $instance->created_at->format('Y-m-d H:i:s'),
                    $instance->app_type
                ];
            }

            $this->table($headers, $rows);

            // Log the results
            Log::info('Unclaimed instances listed successfully', [
                'count' => $count,
                'instances' => $unclaimedInstances->pluck('name')->toArray()
            ]);

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Failed to list unclaimed instances: ' . $e->getMessage());
            Log::error('Failed to list unclaimed instances via command', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return Command::FAILURE;
        }
    }
}
