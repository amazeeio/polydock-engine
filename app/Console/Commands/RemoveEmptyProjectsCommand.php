<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\PolydockAppInstance;
use App\Services\LagoonProjectPurgeService;
use App\Services\PurgeResult;
use FreedomtechHosting\PolydockApp\Enums\PolydockAppInstanceStatus;
use FreedomtechHosting\PolydockApp\PolydockAppLoggerInterface;
use Illuminate\Console\Command;

/**
 * Manual escape hatch for sweeping REMOVED instances and trying to fully
 * delete their Lagoon projects. The same logic is invoked automatically by
 * polydock:dispatch-project-purge, but this command is useful for one-off
 * cleanups (e.g. legacy rows that pre-date the automated flow).
 */
class RemoveEmptyProjectsCommand extends BaseCommand
{
    protected $signature = 'polydock:remove-empty-projects
                          {--dry-run : Show what would be removed without actually doing it}
                          {--force : Skip confirmation prompt}';

    protected $description = 'Find all app instances in state REMOVED with no environments and remove their Lagoon project';

    public function handle(): int
    {
        $isDryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        $this->info('Searching for app instances in REMOVED state...');

        $removedInstances = PolydockAppInstance::where('status', PolydockAppInstanceStatus::REMOVED)->get();

        if ($removedInstances->isEmpty()) {
            $this->info('No app instances found in REMOVED state.');

            return self::SUCCESS;
        }

        $this->info("Found {$removedInstances->count()} app instance(s) in REMOVED state.");
        $this->newLine();

        $service = LagoonProjectPurgeService::makeWithDefaults($this->makeLogger());

        if ($isDryRun) {
            $this->info('Dry-run: probing each project to find empty ones.');
        } else {
            $this->info('Probing each project to find empty ones.');
        }

        // First pass: find which are actually empty.
        $candidates = collect();
        $apiErrorCount = 0;

        foreach ($removedInstances as $instance) {
            $projectName = $service->resolveProjectName($instance);

            if ($projectName === null) {
                $this->warn("Instance {$instance->id} has no project name, skipping.");

                continue;
            }

            $projectProbe = $this->probeEnvironments($service, $projectName);
            if ($projectProbe === null) {
                $apiErrorCount++;

                continue;
            }

            if ($projectProbe['status'] === 'missing') {
                $candidates->push($instance);
                $this->line("✓ Project '{$projectName}' no longer exists in Lagoon (will remove locally)");

                continue;
            }

            if ($projectProbe['status'] === 'empty') {
                $candidates->push($instance);
                $this->line("✓ Project '{$projectName}' has no environments");
            } else {
                $this->line("- Project '{$projectName}' has {$projectProbe['environment_count']} environment(s)");
            }
        }

        if ($apiErrorCount > 0) {
            $this->warn("Warning: {$apiErrorCount} API call(s) failed during environment checking.");
        }

        if ($candidates->isEmpty()) {
            $this->info('No empty projects to clean up.');

            return self::SUCCESS;
        }

        $this->info("Found {$candidates->count()} empty project(s) ready for cleanup.");
        $this->newLine();

        $headers = ['ID', 'Name', 'Project Name', 'Store App', 'Removed At'];
        $rows = [];

        foreach ($candidates as $instance) {
            $rows[] = [
                $instance->id,
                $instance->name ?: 'N/A',
                $service->resolveProjectName($instance) ?? 'N/A',
                $instance->storeApp->name ?? 'N/A',
                ($instance->removed_at ?? $instance->updated_at)?->format('Y-m-d H:i:s') ?? 'N/A',
            ];
        }

        $this->table($headers, $rows);
        $this->newLine();

        if ($isDryRun) {
            $this->info('DRY RUN: The projects listed above would be deleted.');

            return self::SUCCESS;
        }

        if (! $force) {
            $confirmed = $this->confirm(
                "Are you sure you want to remove these {$candidates->count()} empty Lagoon project(s)?",
                false,
            );

            if (! $confirmed) {
                $this->info('Operation cancelled.');

                return self::SUCCESS;
            }
        }

        $successCount = 0;
        $errorCount = 0;

        foreach ($candidates as $instance) {
            $result = $service->attemptPurge($instance);

            switch ($result) {
                case PurgeResult::Purged:
                case PurgeResult::AlreadyGone:
                    $instance->setStatus(
                        PolydockAppInstanceStatus::REMOVED,
                        $result === PurgeResult::AlreadyGone
                            ? 'Lagoon project already deleted (manual sweep)'
                            : 'Lagoon project deleted (manual sweep)',
                    );
                    $instance->save();
                    $instance->delete();
                    $this->info("✓ Purged instance {$instance->id} (".($service->resolveProjectName($instance) ?? 'n/a').')');
                    $successCount++;
                    break;

                case PurgeResult::StillHasEnvironments:
                    // Project picked up envs between probe and delete. Skip.
                    $this->warn(
                        "- Project for instance {$instance->id} now has environments again, skipping",
                    );
                    break;

                default:
                    $this->error(
                        "✗ Failed to purge instance {$instance->id}: ".($service->lastFailureReason ?? 'unknown'),
                    );
                    $errorCount++;
                    break;
            }
        }

        $this->newLine();
        $this->info('Operation completed:');
        $this->info("- Successfully processed: {$successCount}");
        if ($errorCount > 0) {
            $this->warn("- Failed to process: {$errorCount}");
        }

        return $errorCount > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Quick probe of the env list.
     *
     * Returns:
     *  - ['status' => 'missing'] when Lagoon no longer has this project
     *  - ['status' => 'empty', 'environment_count' => 0] for existing empty projects
     *  - ['status' => 'has_environments', 'environment_count' => N]
     *  - null on probe failure
     */
    protected function probeEnvironments(LagoonProjectPurgeService $service, string $projectName): ?array
    {
        try {
            $data = $service->getProjectByName($projectName);

            if (is_array($data) && array_key_exists('projectByName', $data)) {
                $data = $data['projectByName'];
            }
            if (empty($data)) {
                return ['status' => 'missing'];
            }

            if (! is_array($data)) {
                $this->error("Probe failed for {$projectName}: unexpected payload type");

                return null;
            }

            if (isset($data['error']) && $data['error']) {
                $this->error("Probe failed for {$projectName}: ".json_encode($data['error']));

                return null;
            }

            if (! array_key_exists('environments', $data) || ! is_array($data['environments'])) {
                $this->error("Probe failed for {$projectName}: missing environments in Lagoon response");

                return null;
            }

            $environments = $data['environments'];
            $activeEnvironments = array_filter($environments, [LagoonProjectPurgeService::class, 'isActiveEnvironment']);

            $activeEnvironmentCount = count($activeEnvironments);

            if ($activeEnvironmentCount === 0) {
                return ['status' => 'empty', 'environment_count' => 0];
            }

            return ['status' => 'has_environments', 'environment_count' => $activeEnvironmentCount];
        } catch (\Throwable $e) {
            $this->error("Probe failed for {$projectName}: {$e->getMessage()}");

            return null;
        }
    }

    protected function makeLogger(): PolydockAppLoggerInterface
    {
        return new class($this) implements PolydockAppLoggerInterface
        {
            public function __construct(private $command) {}

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
                $this->command->info('debug - '.$message);
            }
        };
    }
}
