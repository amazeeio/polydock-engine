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
    protected $signature = 'polydock:list-unclaimed-instances {--days=14 : Number of days old instances must be} {--app= : PolydockStoreApp ID to limit search to}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List all unclaimed PolydockAppInstances that are older than specified days';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = (int) $this->option('days');
        $this->info("Searching for unclaimed PolydockAppInstances older than {$days} days...");
        
        Log::info('Listing unclaimed PolydockAppInstances via command');

        try {
            $appId = $this->option('app');
            
            // Build the query for unclaimed instances older than specified days
            $query = PolydockAppInstance::where('status', PolydockAppInstanceStatus::RUNNING_HEALTHY_UNCLAIMED)
                ->whereDate('created_at', '<=', now()->subDay($days));
            
            // Add app filter if specified
            if ($appId) {
                $query->where('polydock_store_app_id', $appId);
            }
            
            // Execute query
            $unclaimedInstances = $query->get();

            $count = $unclaimedInstances->count();
            
            if ($count === 0) {
                $appFilterText = $appId ? " for app ID {$appId}" : "";
                $this->info("No unclaimed instances found that are older than {$days} days{$appFilterText}.");
                Log::info("No unclaimed instances found older than {$days} days" . ($appId ? " for app ID {$appId}" : ""));
                return Command::SUCCESS;
            }

            $appFilterText = $appId ? " for app ID {$appId}" : "";
            $this->info("Found {$count} unclaimed instances{$appFilterText}:");
            $this->newLine();

            // Display the instances in a table format
            $headers = ['ID', 'Name', 'Store','Status', 'Created At', 'App Type'];
            $rows = [];

            foreach ($unclaimedInstances as $instance) {
                $rows[] = [
                    $instance->id,
                    $instance->name,
                    $instance->storeApp->store->name . " - " . $instance->storeApp->name . "(" . $instance->storeApp->id . ")",
                    $instance->status->value,
                    $instance->created_at->format('Y-m-d H:i:s'),
                    $instance->app_type
                ];
            }

            $this->table($headers, $rows);

            // Log the results
            Log::info('Unclaimed instances listed successfully', [
                'days' => $days,
                'app_id' => $appId,
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
