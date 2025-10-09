<?php

namespace App\Console\Commands;

use App\Models\PolydockAppInstance;
use FreedomtechHosting\PolydockApp\Enums\PolydockAppInstanceStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RemoveAppInstancesByEmail extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'polydock:remove-instances-by-email
                          {email : The email address to search for}
                          {--dry-run : Show what would be removed without actually doing it}
                          {--force : Skip confirmation prompt}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Find all app instances with the given email address and set them to pending removal';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = $this->argument('email');
        $isDryRun = $this->option('dry-run');
        $force = $this->option('force');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Invalid email address provided.');
            return 1;
        }

        $this->info("Searching for app instances with email: {$email}");

        // Find all app instances with the given email in their data
        $instances = PolydockAppInstance::whereJsonContains('data->user-email', $email)->get();

        
        if ($instances->isEmpty()) {
            $this->info('No app instances found with the email address: ' . $email);
            return 0;
        }

        // Filter out instances already in removal states
        $removableInstances = $instances->filter(function ($instance) {
            return !in_array($instance->status, PolydockAppInstance::$stageRemoveStatuses);
        });

        $alreadyRemovingCount = $instances->count() - $removableInstances->count();
        
        if ($alreadyRemovingCount > 0) {
            $this->warn("Found {$alreadyRemovingCount} instance(s) already in removal state - skipping these.");
        }

        if ($removableInstances->isEmpty()) {
            $this->info('All found instances are already in removal state. Nothing to do.');
            return 0;
        }

        $this->info("Found {$instances->count()} app instance(s) with email: {$email}");
        $this->info("Removable instances: {$removableInstances->count()}");
        $this->newLine();

        // Display the removable instances
        $headers = ['ID', 'Name', 'Status', 'Store App', 'Created At'];
        $rows = [];

        foreach ($removableInstances as $instance) {
            $rows[] = [
                $instance->id,
                $instance->name ?: 'N/A',
                $instance->status->getLabel(),
                $instance->storeApp->name ?? 'N/A',
                $instance->created_at->format('Y-m-d H:i:s')
            ];
        }

        $this->table($headers, $rows);
        $this->newLine();

        if ($isDryRun) {
            $this->info('DRY RUN: These instances would be set to PENDING_PRE_REMOVE status.');
            return 0;
        }

        // Confirm removal unless force flag is used
        if (!$force) {
            $confirmed = $this->confirm(
                "Are you sure you want to set these {$removableInstances->count()} app instance(s) to PENDING_PRE_REMOVE status?",
                false
            );

            if (!$confirmed) {
                $this->info('Operation cancelled.');
                return 0;
            }
        }

        // Set instances to pending removal
        $successCount = 0;
        $errorCount = 0;

        foreach ($removableInstances as $instance) {
            try {
                $previousStatus = $instance->status;
                
                $instance->setStatus(
                    PolydockAppInstanceStatus::PENDING_PRE_REMOVE,
                    "Marked for removal by email: {$email}"
                );
                $instance->save();

                $this->info("✓ Instance {$instance->id} ({$instance->name}) set to PENDING_PRE_REMOVE (was: {$previousStatus->getLabel()})");
                $successCount++;
            } catch (\Exception $e) {
                $this->error("✗ Failed to update instance {$instance->id}: {$e->getMessage()}");
                $errorCount++;
            }
        }

        $this->newLine();
        $this->info("Operation completed:");
        $this->info("- Successfully updated: {$successCount}");
        if ($errorCount > 0) {
            $this->warn("- Failed to update: {$errorCount}");
        }

        return $errorCount > 0 ? 1 : 0;
    }
}