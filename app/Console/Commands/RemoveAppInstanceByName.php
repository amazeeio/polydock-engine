<?php

namespace App\Console\Commands;

use App\Models\PolydockAppInstance;
use FreedomtechHosting\PolydockApp\Enums\PolydockAppInstanceStatus;
use Illuminate\Console\Command;

class RemoveAppInstanceByName extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'polydock:remove-instance-by-name
                          {name : The app instance name to search for}
                          {--dry-run : Show what would be removed without actually doing it}
                          {--force : Skip confirmation prompt}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Find the app instance with the given name and set it to pending removal';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $name = $this->argument('name');
        $isDryRun = $this->option('dry-run');
        $force = $this->option('force');

        if (empty($name)) {
            $this->error('App instance name cannot be empty.');

            return 1;
        }

        $this->info("Searching for app instance with name: {$name}");

        // Find the app instance with the given name
        $instance = PolydockAppInstance::where('name', $name)->first();

        if (! $instance) {
            $this->info('No app instance found with the name: '.$name);

            return 0;
        }

        // Check if instance is already in removal state
        if (in_array($instance->status, PolydockAppInstance::$stageRemoveStatuses)) {
            $this->warn("Instance '{$name}' is already in removal state ({$instance->status->getLabel()}). Nothing to do.");

            return 0;
        }

        $this->info("Found app instance: {$name}");
        $this->newLine();

        // Display the instance
        $headers = ['ID', 'Name', 'Status', 'Store App', 'Created At'];
        $rows = [[
            $instance->id,
            $instance->name ?: 'N/A',
            $instance->status->getLabel(),
            $instance->storeApp->name ?? 'N/A',
            $instance->created_at->format('Y-m-d H:i:s'),
        ]];

        $this->table($headers, $rows);
        $this->newLine();

        if ($isDryRun) {
            $this->info('DRY RUN: This instance would be set to PENDING_PRE_REMOVE status.');

            return 0;
        }

        // Confirm removal unless force flag is used
        if (! $force) {
            $confirmed = $this->confirm(
                "Are you sure you want to set instance '{$name}' to PENDING_PRE_REMOVE status?",
                false
            );

            if (! $confirmed) {
                $this->info('Operation cancelled.');

                return 0;
            }
        }

        // Set instance to pending removal
        try {
            $previousStatus = $instance->status;

            $instance->setStatus(
                PolydockAppInstanceStatus::PENDING_PRE_REMOVE,
                "Marked for removal by name: {$name}"
            );
            $instance->save();

            $this->info("✓ Instance {$instance->id} ({$instance->name}) set to PENDING_PRE_REMOVE (was: {$previousStatus->getLabel()})");
            $this->newLine();
            $this->info('Operation completed successfully.');

            return 0;
        } catch (\Exception $e) {
            $this->error("✗ Failed to update instance {$instance->id}: {$e->getMessage()}");

            return 1;
        }
    }
}
