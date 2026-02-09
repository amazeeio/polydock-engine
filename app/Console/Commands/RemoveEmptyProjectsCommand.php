<?php

namespace App\Console\Commands;

use App\Models\PolydockAppInstance;
use App\PolydockServiceProviders\PolydockServiceProviderFTLagoon;
use FreedomtechHosting\PolydockApp\Enums\PolydockAppInstanceStatus;
use FreedomtechHosting\PolydockApp\PolydockAppLoggerInterface;
use Illuminate\Console\Command;

class RemoveEmptyProjectsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'polydock:remove-empty-projects
                          {--dry-run : Show what would be removed without actually doing it}
                          {--force : Skip confirmation prompt}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Find all app instances in state REMOVED with no environments and remove their Lagoon project';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        $force = $this->option('force');

        $this->info('Searching for app instances in REMOVED state with no environments...');

        // Find all app instances in REMOVED state
        $removedInstances = PolydockAppInstance::where('status', PolydockAppInstanceStatus::REMOVED)->get();

        if ($removedInstances->isEmpty()) {
            $this->info('No app instances found in REMOVED state.');

            return 0;
        }

        $this->info("Found {$removedInstances->count()} app instance(s) in REMOVED state.");
        $this->newLine();

        $lagoonServiceProvider = new PolydockServiceProviderFTLagoon(
            config('polydock.service_providers_singletons.PolydockServiceProviderFTLagoon'),
            $this->getLogger(),
        );

        $lagoonClient = $lagoonServiceProvider->getLagoonClient();

        // Filter instances that have no environments (empty projects)
        $emptyProjects = collect();
        $apiErrorCount = 0;

        foreach ($removedInstances as $instance) {
            try {
                $projectName = $instance->data['project_name'] ?? $instance->name;

                if (! $projectName) {
                    $this->warn("Instance {$instance->id} has no project name, skipping.");

                    continue;
                }

                // Get project details from Lagoon API
                $projectData = $lagoonClient->getProjectByName($projectName);

                // Check if project has any environments
                $environments = $projectData['environments'] ?? [];

                if (empty($environments)) {
                    $emptyProjects->push($instance);
                    $this->line("✓ Project '{$projectName}' has no environments - marked for cleanup");
                } else {
                    $this->line("- Project '{$projectName}' has ".count($environments).' environment(s) - keeping');
                }
            } catch (\Exception $e) {
                $this->error("✗ Failed to check project for instance {$instance->id}: {$e->getMessage()}");
                $apiErrorCount++;

                // Continue processing other instances even if one fails
            }
        }

        if ($apiErrorCount > 0) {
            $this->warn("Warning: {$apiErrorCount} API call(s) failed during environment checking.");
        }

        if ($emptyProjects->isEmpty()) {
            $this->info('All REMOVED instances still have environments. No empty projects to clean up.');

            return 0;
        }

        $this->info("Found {$emptyProjects->count()} empty project(s) ready for cleanup.");
        $this->newLine();

        // Display the empty projects
        $headers = ['ID', 'Name', 'Project Name', 'Store App', 'Removed At'];
        $rows = [];

        foreach ($emptyProjects as $instance) {
            $rows[] = [
                $instance->id,
                $instance->name ?: 'N/A',
                $instance->data['project_name'] ?? 'N/A',
                $instance->storeApp->name ?? 'N/A',
                $instance->updated_at->format('Y-m-d H:i:s'),
            ];
        }

        $this->table($headers, $rows);
        $this->newLine();

        if ($isDryRun) {
            $this->info('DRY RUN: The projects listed above would be deleted.');

            return 0;
        }

        // Confirm removal unless force flag is used
        if (! $force) {
            $confirmed = $this->confirm(
                "Are you sure you want to remove these {$emptyProjects->count()} empty Lagoon project(s)?",
                false,
            );

            if (! $confirmed) {
                $this->info('Operation cancelled.');

                return 0;
            }
        }

        // Remove empty projects
        $successCount = 0;
        $errorCount = 0;

        foreach ($emptyProjects as $instance) {
            try {
                $projectName = $instance->data['project_name'] ?? $instance->name;

                // Remove the empty project from Lagoon
                $deleteResponse = $lagoonClient->deleteProjectByName($projectName);

                if (isset($deleteResponse['error'])) {
                    $this->error(
                        "✗ Failed to delete Lagoon project '{$projectName}': ".json_encode($deleteResponse['error']),
                    );
                    $errorCount++;
                } else {
                    $this->info("✓ Successfully removed Lagoon project: {$projectName} (Instance ID: {$instance->id})");

                    // TODO: I think we might want to introduce a soft-deleted kind of process here
                    // ideally we'd want some record that the instance existed, surely, but not have it show up
                    // anywhere.

                    $successCount++;
                }
            } catch (\Exception $e) {
                $this->error("✗ Failed to remove project for instance {$instance->id}: {$e->getMessage()}");
                $errorCount++;
            }
        }

        $this->newLine();
        $this->info('Operation completed:');
        $this->info("- Successfully processed: {$successCount}");
        if ($errorCount > 0) {
            $this->warn("- Failed to process: {$errorCount}");
        }

        return $errorCount > 0 ? 1 : 0;
    }

    protected function getLogger(): PolydockAppLoggerInterface
    {
        // Create logger that delegates to command output methods
        $logger = new class($this) implements PolydockAppLoggerInterface
        {
            public function __construct(
                private $command,
            ) {}

            public function info(string $message, array $context = []): void
            {
                $this->command->info($message);
            }

            public function error(string $message, array $context = []): void
            {
                $this->command->error($message);
            }

            public function warning(string $message, array $context = []): void
            {
                $this->command->warn($message);
            }

            public function debug(string $message, array $context = []): void
            {
                $this->command->info(sprintf('debug - %s', $message));
            }
        };

        return $logger;
    }
}
