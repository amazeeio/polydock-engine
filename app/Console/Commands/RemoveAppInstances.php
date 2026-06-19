<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\PolydockAppInstance;
use App\Models\PolydockStoreApp;
use FreedomtechHosting\PolydockApp\Enums\PolydockAppInstanceStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class RemoveAppInstances extends BaseCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'polydock:remove-instances
                            {--app= : The PolydockStoreApp UUID to search for}
                            {--email= : The user email address or pattern to search for (supports % wildcards)}
                            {--name= : The exact app instance name to search for}
                            {--uuid= : The exact app instance UUID to search for}
                            {--limit= : Limit the number of instances to fetch and remove in one go}
                            {--force-purge : Sets immediate force-purge fields to bypass grace period}
                            {--dry-run : Show what would be removed without actually doing it}
                            {--force : Skip confirmation prompt}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Find and set active app instances to pending removal using various filter criteria';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $appInput = $this->option('app');
        $email = $this->option('email');
        $name = $this->option('name');
        $uuid = $this->option('uuid');
        $limit = $this->option('limit');
        $isDryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');
        $forcePurge = (bool) $this->option('force-purge');

        if (empty($appInput) && empty($email) && empty($name) && empty($uuid)) {
            $this->error('You must specify at least one filter option: --app, --email, --name, or --uuid.');

            return self::FAILURE;
        }

        if ($limit !== null && $limit !== '') {
            if (! preg_match('/^\d+$/', (string) $limit) || (int) $limit <= 0) {
                $this->error('The --limit option must be a positive integer.');

                return self::FAILURE;
            }
        }

        $query = PolydockAppInstance::query();
        $filtersApplied = [];

        // 1. Resolve --app if specified
        if (! empty($appInput)) {
            if (! Str::isUuid($appInput)) {
                $this->error('The --app option must be a valid PolydockStoreApp UUID.');

                return self::FAILURE;
            }

            $storeApp = PolydockStoreApp::where('uuid', $appInput)->first();

            if (! $storeApp) {
                $this->error("No PolydockStoreApp found with UUID: {$appInput}");

                return self::FAILURE;
            }
            $query->where('polydock_store_app_id', $storeApp->id);
            $filtersApplied[] = "App: {$storeApp->name} (ID: {$storeApp->id})";
        }

        // 2. Resolve --email if specified
        if (! empty($email)) {
            $isPattern = str_contains($email, '%');
            if ($isPattern) {
                // Use raw SQL for pattern matching (compatible with SQLite and MySQL/MariaDB)
                if (DB::getDriverName() === 'sqlite') {
                    $query->where(
                        DB::raw('json_extract(data, \'$."user-email"\')'),
                        'LIKE',
                        $email
                    );
                } else {
                    $query->where(
                        DB::raw('JSON_UNQUOTE(JSON_EXTRACT(data, \'$."user-email"\'))'),
                        'LIKE',
                        $email
                    );
                }
            } else {
                if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $this->error('Invalid email address provided. Use % for wildcard patterns (e.g., %@example.com).');

                    return self::FAILURE;
                }
                $query->whereJsonContains('data->user-email', $email);
            }
            $filtersApplied[] = "Email: {$email}";
        }

        // 3. Resolve --name if specified
        if (! empty($name)) {
            $query->where('name', $name);
            $filtersApplied[] = "Instance Name: {$name}";
        }

        // 4. Resolve --uuid if specified
        if (! empty($uuid)) {
            $query->where('uuid', $uuid);
            $filtersApplied[] = "UUID: {$uuid}";
        }

        // Filter out instances already in removal or purge states
        $query->whereNotIn('status', array_merge(
            PolydockAppInstance::$stageRemoveStatuses,
            PolydockAppInstance::$stagePurgeStatuses
        ));

        // Apply limit if specified
        if ($limit !== null && $limit !== '') {
            $query->orderBy('id', 'asc')->limit((int) $limit);
            $filtersApplied[] = "Limit: {$limit}";
        }

        $this->info('Searching for active app instances matching: '.implode(', ', $filtersApplied));

        $instances = $query->get();

        if ($instances->isEmpty()) {
            $this->info('No active app instances found matching the specified filters.');

            return self::SUCCESS;
        }

        $count = $instances->count();
        $this->info("Found {$count} active app instance(s):");
        $this->newLine();

        // Display the instances in a table
        $headers = ['ID', 'Name', 'Email', 'Status', 'Store App', 'Created At'];
        $rows = [];
        foreach ($instances as $instance) {
            $rows[] = [
                $instance->id,
                $instance->name ?: 'N/A',
                $instance->getUserEmail() ?: 'N/A',
                $instance->status->getLabel(),
                $instance->storeApp->name ?? 'N/A',
                $instance->created_at->format('Y-m-d H:i:s'),
            ];
        }

        $this->table($headers, $rows);
        $this->newLine();

        if ($isDryRun) {
            $this->info('DRY RUN: These instances would be set to PENDING_PRE_REMOVE status.');

            return self::SUCCESS;
        }

        // Confirmation prompt unless force option is passed
        if (! $force) {
            $confirmed = $this->confirm(
                "Are you sure you want to set these {$count} active app instance(s) to PENDING_PRE_REMOVE status?",
                false
            );

            if (! $confirmed) {
                $this->info('Operation cancelled.');

                return self::SUCCESS;
            }
        }

        // Mutate status to pending removal
        $successCount = 0;
        $errorCount = 0;

        foreach ($instances as $instance) {
            try {
                $previousStatus = $instance->status;

                if ($forcePurge) {
                    $instance->force_purge_requested_at = now();
                    $instance->purge_eligible_at = now();
                }

                $reason = 'Marked for removal via unified remove command with filters: '.implode(', ', $filtersApplied);
                $instance->setStatus(
                    PolydockAppInstanceStatus::PENDING_PRE_REMOVE,
                    $reason
                );
                $instance->save();

                $suffix = $forcePurge ? ' (immediate purge requested)' : '';
                $this->info(
                    "✓ Instance {$instance->id} ({$instance->name}) set to PENDING_PRE_REMOVE (was: {$previousStatus->getLabel()}){$suffix}"
                );
                $successCount++;
            } catch (\Exception $e) {
                $this->error("✗ Failed to update instance {$instance->id}: {$e->getMessage()}");
                Log::error("Failed to remove app instance {$instance->id} via console command", [
                    'instance_id' => $instance->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                $errorCount++;
            }
        }

        $this->newLine();
        if ($errorCount > 0) {
            if ($successCount > 0) {
                $this->error('Operation completed with errors (partial failure).');
            } else {
                $this->error('Operation failed.');
            }
        } else {
            $this->info('Operation completed successfully.');
        }

        $this->info("- Successfully updated: {$successCount}");
        if ($errorCount > 0) {
            $this->error("- Failed to update: {$errorCount}");
        }

        return $errorCount > 0 ? self::FAILURE : self::SUCCESS;
    }
}
