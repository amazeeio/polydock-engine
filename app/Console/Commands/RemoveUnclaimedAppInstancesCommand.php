<?php

namespace App\Console\Commands;

use App\Models\PolydockAppInstance;
use FreedomtechHosting\PolydockApp\Enums\PolydockAppInstanceStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RemoveUnclaimedAppInstancesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'polydock:remove-unclaimed-instances {--force : Run without interactive confirmation} {--limit=3 : Maximum number of instances to process} {--days=14 : Number of days old instances must be} {--app= : PolydockStoreApp ID to limit search to}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Set unclaimed PolydockAppInstances older than specified days to pending removal status';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = (int) $this->option('days');
        $this->info("Searching for unclaimed PolydockAppInstances older than {$days} days...");
        
        Log::info('Setting unclaimed PolydockAppInstances to pending removal via command');

        try {
            // Get the limit from command line option
            $limit = (int) $this->option('limit');
            $appId = $this->option('app');
            
            // Build the query for unclaimed instances older than specified days
            $query = PolydockAppInstance::where('status', PolydockAppInstanceStatus::RUNNING_HEALTHY_UNCLAIMED)
                ->whereDate('created_at', '<=', now()->subDay($days));
            
            // Add app filter if specified
            if ($appId) {
                $query->where('polydock_store_app_id', $appId);
            }
            
            // Apply limit and execute query
            $unclaimedInstances = $query->limit($limit)->get();

            $count = $unclaimedInstances->count();
            
            if ($count === 0) {
                $appFilterText = $appId ? " for app ID {$appId}" : "";
                $this->info("No unclaimed instances found that are older than {$days} days{$appFilterText}.");
                Log::info("No unclaimed instances found older than {$days} days" . ($appId ? " for app ID {$appId}" : ""));
                return Command::SUCCESS;
            }

            $appFilterText = $appId ? " for app ID {$appId}" : "";
            $this->info("Found {$count} unclaimed instances{$appFilterText} (limit: {$limit}):");
            $this->newLine();

            // Display the instances that will be affected
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

            // Check if running in non-interactive mode
            if ($this->option('force')) {
                $this->info('Running in non-interactive mode (--force flag detected)');
                $confirmed = true;
            } else {
                // Ask for confirmation
                $confirmed = $this->confirm("Do you want to set these {$count} instances to pending removal status?");
            }

            if (!$confirmed) {
                $this->info('Operation cancelled by user.');
                Log::info('Remove unclaimed instances operation cancelled by user');
                return Command::SUCCESS;
            }

            // Set status to pending removal for all instances
            $updatedCount = 0;
            foreach ($unclaimedInstances as $instance) {
                try {
                    $instance->setStatus(PolydockAppInstanceStatus::PENDING_PRE_REMOVE);
                    $instance->user_group_id = config('polydock.default_user_group_id_for_unallocated_instances',1); 
                    $instance->save();
                    $updatedCount++;
                    
                    $this->line("✓ Updated instance: {$instance->name} (ID: {$instance->id})");
                } catch (\Exception $e) {
                    $this->error("✗ Failed to update instance {$instance->name} (ID: {$instance->id}): " . $e->getMessage());
                    Log::error('Failed to update instance status', [
                        'instance_id' => $instance->id,
                        'instance_name' => $instance->name,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            $this->newLine();
            $this->info("Successfully updated {$updatedCount} out of {$count} instances to pending removal status.");

            // Log the results
            Log::info('Unclaimed instances set to pending removal successfully', [
                'days' => $days,
                'limit' => $limit,
                'app_id' => $appId,
                'total_found' => $count,
                'successfully_updated' => $updatedCount,
                'failed_updates' => $count - $updatedCount,
                'instances' => $unclaimedInstances->pluck('name')->toArray()
            ]);

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Failed to process unclaimed instances: ' . $e->getMessage());
            Log::error('Failed to process unclaimed instances via command', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return Command::FAILURE;
        }
    }
}
