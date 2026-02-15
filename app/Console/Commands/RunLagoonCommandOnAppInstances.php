<?php

namespace App\Console\Commands;

use App\Models\PolydockAppInstance;
use App\Models\PolydockStoreApp;
use FreedomtechHosting\FtLagoonPhp\Ssh;
use Illuminate\Console\Command;
use Illuminate\Process\Pool;
use Illuminate\Support\Facades\Process;

use function Laravel\Prompts\multiselect;

class RunLagoonCommandOnAppInstances extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'polydock:instances:run-lagoon-command
                            {app_uuid : The UUID of the store app}
                            {cmd : The command to run on the remote instances}
                            {--environment= : Optional environment override}
                            {--force : Force execution without confirmation}
                            {--concurrency=1 : Number of concurrent processes to run (default: 1 for serial execution)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run a command via Lagoon CLI on all running instances of a specific app';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $appUuid = $this->argument('app_uuid');
        $commandToRun = $this->argument('cmd');
        $envOverride = $this->option('environment');
        $concurrency = (int) $this->option('concurrency');

        $storeApp = PolydockStoreApp::where('uuid', $appUuid)->first();
        if (! $storeApp) {
            $this->error("Store App with UUID {$appUuid} not found.");

            return 1;
        }

        $this->info("Found Store App: {$storeApp->name} (ID: {$storeApp->id})");

        // Get running instances
        $instances = PolydockAppInstance::where('polydock_store_app_id', $storeApp->id)
            ->whereIn('status', PolydockAppInstance::$stageRunningStatuses)
            ->get();

        $count = $instances->count();
        if ($count === 0) {
            $this->info('No running instances found for this app.');

            return 0;
        }

        $this->info("Found {$count} running instances.");

        if (! $this->option('force')) {
            // Pre-calculate column widths
            $maxWidths = [
                'id' => strlen('ID'),
                'name' => strlen('Name'),
                'project' => strlen('Lagoon Project'),
                'branch' => strlen('Branch'),
            ];

            $instanceData = [];

            foreach ($instances as $instance) {
                $projectName = $instance->getKeyValue('lagoon-project-name');
                $branch = $envOverride ?: $instance->getKeyValue('lagoon-deploy-branch');

                $data = [
                    'id' => (string) $instance->id,
                    'name' => $instance->name,
                    'project' => $projectName,
                    'branch' => $branch,
                ];

                $instanceData[$instance->id] = $data;

                foreach ($maxWidths as $key => $width) {
                    $maxWidths[$key] = max($width, strlen((string) $data[$key]));
                }
            }

            // Create formatted options
            $options = [];
            foreach ($instanceData as $id => $data) {
                $label = sprintf(
                    '%s  %s  %s  %s',
                    str_pad($data['id'], $maxWidths['id']),
                    str_pad((string) $data['name'], $maxWidths['name']),
                    str_pad((string) $data['project'], $maxWidths['project']),
                    str_pad((string) $data['branch'], $maxWidths['branch'])
                );
                $options[$id] = $label;
            }

            $header = sprintf(
                '%s  %s  %s  %s',
                str_pad('ID', $maxWidths['id']),
                str_pad('Name', $maxWidths['name']),
                str_pad('Lagoon Project', $maxWidths['project']),
                str_pad('Branch', $maxWidths['branch'])
            );

            $selectedIds = multiselect(
                label: 'Select instances to run the command on:',
                options: $options,
                default: array_keys($options),
                scroll: 15,
                hint: $header
            );

            if (empty($selectedIds)) {
                $this->info('No instances selected.');

                return 0;
            }

            // Filter instances to only those selected
            $instances = $instances->whereIn('id', $selectedIds);
            $count = $instances->count();

            if (! $this->confirm("Are you sure you want to run '{$commandToRun}' on {$count} selected instances?")) {
                $this->info('Operation cancelled.');

                return 0;
            }
        } else {
            // Force mode: show table for audit/info purposes
            $headers = ['ID', 'Name', 'Lagoon Project', 'Branch'];
            $rows = [];

            foreach ($instances as $instance) {
                $projectName = $instance->getKeyValue('lagoon-project-name');
                $branch = $envOverride ?: $instance->getKeyValue('lagoon-deploy-branch');
                $rows[] = [$instance->id, $instance->name, $projectName, $branch];
            }

            $this->table($headers, $rows);
        }

        // Configuration
        $sshConfig = config('polydock.service_providers_singletons.PolydockServiceProviderFTLagoon', []);
        $sshHost = $sshConfig['ssh_server'] ?? 'ssh.lagoon.amazeeio.cloud';
        $sshPort = $sshConfig['ssh_port'] ?? '32222';
        $globalKeyFile = $sshConfig['ssh_private_key_file'] ?? null;

        // Prepare temp files array to clean up later
        $tempKeyFiles = [];

        try {
            if ($concurrency > 1) {
                $this->info("Running commands concurrently on {$count} instances (concurrency: {$concurrency})...");

                $pool = Process::pool(function (Pool $pool) use ($instances, $envOverride, $commandToRun, $sshHost, $sshPort, $globalKeyFile, &$tempKeyFiles) {
                    foreach ($instances as $instance) {
                        $fullCommand = $this->getLagoonSshCommand($instance, $commandToRun, $envOverride, $sshHost, $sshPort, $globalKeyFile, $tempKeyFiles);

                        if ($fullCommand) {
                            $pool->as($instance->id)->command($fullCommand);
                        } else {
                            $this->error("\nMissing project name or branch for instance {$instance->id} ({$instance->name})");
                        }
                    }
                });

                try {
                    // Handle potential issues with Process::fake() returning an object that doesn't support concurrency
                    $pool->concurrency($concurrency);
                } catch (\Throwable) {
                    // Ignore if method doesn't exist (e.g. in tests)
                }

                $poolResults = $pool->wait();

                foreach ($poolResults as $instanceId => $result) {
                    $instance = $instances->find($instanceId);
                    // If instance was skipped (no command generated), result might not exist or need handling?
                    // Actually pool results only contain what was added to the pool.
                    // If we skipped adding it to the pool, it won't be in $poolResults.
                    if (! $instance) {
                        continue;
                    }

                    $projectName = $instance->getKeyValue('lagoon-project-name');

                    if ($result->successful()) {
                        if ($this->output->isVerbose()) {
                            $this->info("\n[SUCCESS] {$projectName}: ".trim((string) $result->output()));
                        }
                    } else {
                        $this->error("\n[FAILED] {$projectName}: ".trim((string) $result->errorOutput()));
                    }
                }

                $this->info('Done.');

                return 0;
            }

            $bar = $this->output->createProgressBar($count);
            $bar->start();

            foreach ($instances as $instance) {
                $projectName = $instance->getKeyValue('lagoon-project-name');
                $fullCommand = $this->getLagoonSshCommand($instance, $commandToRun, $envOverride, $sshHost, $sshPort, $globalKeyFile, $tempKeyFiles);

                if (! $fullCommand) {
                    $this->error("\nMissing project name or branch for instance {$instance->id} ({$instance->name})");
                    $bar->advance();

                    continue;
                }

                // Log what we are doing
                // $this->line("\nExecuting on {$projectName} ({$branch})...");

                $result = Process::run($fullCommand);

                if ($result->successful()) {
                    if ($this->output->isVerbose()) {
                        $this->info("\n[SUCCESS] {$projectName}: ".trim($result->output()));
                    }
                } else {
                    $this->error("\n[FAILED] {$projectName}: ".trim($result->errorOutput()));
                }

                $bar->advance();
            }

            $bar->finish();
            $this->newLine();
            $this->info('Done.');

            return 0;
        } finally {
            // Cleanup temp files
            foreach ($tempKeyFiles as $file) {
                if (file_exists($file)) {
                    @unlink($file);
                }
            }
        }
    }

    /**
     * Helper to construct the Lagoon SSH command using the library.
     * Returns null if project name or branch is missing.
     */
    protected function getLagoonSshCommand(
        PolydockAppInstance $instance,
        string $commandToRun,
        ?string $envOverride,
        string $sshHost,
        string $sshPort,
        ?string $globalKeyFile,
        array &$tempKeyFiles
    ): ?string {
        $projectName = $instance->getKeyValue('lagoon-project-name');
        $branch = $envOverride ?: $instance->getKeyValue('lagoon-deploy-branch');

        if (empty($projectName) || empty($branch)) {
            return null;
        }

        // Construct SSH user (project-environment)
        // Replace / with - in branch name as per Lagoon convention
        $sshUser = $projectName.'-'.str_replace('/', '-', $branch);

        // Determine private key
        $privateKeyContent = $instance->getKeyValue('lagoon-deploy-private-key');
        $privateKeyFile = $globalKeyFile;

        if (! empty($privateKeyContent)) {
            // Create temp file for the key
            $tempFile = tempnam(sys_get_temp_dir(), 'lagoon_key_');
            if ($tempFile === false) {
                $this->error("Failed to create temporary key file for instance {$instance->id}");

                return null;
            }
            file_put_contents($tempFile, $privateKeyContent);
            chmod($tempFile, 0600); // Secure the key file
            $tempKeyFiles[] = $tempFile;
            $privateKeyFile = $tempFile;
        }

        if (empty($privateKeyFile)) {
            $this->error("No private key found for instance {$instance->id} and no global key configured.");

            return null;
        }

        try {
            // Use the library to create the SSH command
            $ssh = Ssh::createLagoonConfigured($sshUser, $sshHost, $sshPort, $privateKeyFile);

            return $ssh->getCommandForExecute($commandToRun);
        } catch (\Exception $e) {
            $this->error("Failed to create SSH command for instance {$instance->id}: {$e->getMessage()}");

            return null;
        }
    }
}
