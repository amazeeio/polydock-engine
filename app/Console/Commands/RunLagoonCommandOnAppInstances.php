<?php

namespace App\Console\Commands;

use App\Models\PolydockAppInstance;
use App\Models\PolydockStoreApp;
use FreedomtechHosting\FtLagoonPhp\Client;
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
                            {--environment= : Optional environment override}
                            {--force : Force execution without confirmation}
                            {--variables-only : Only deploy variables}
                            {--concurrency=1 : Number of concurrent processes to run (default: 1 for serial execution)}
                            {--instance-id= : (Internal) Run for a specific instance ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Trigger a deployment via Lagoon API on all running instances of a specific app';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $appUuid = $this->argument('app_uuid');
        $envOverride = $this->option('environment');
        $variablesOnly = $this->option('variables-only');
        $concurrency = (int) $this->option('concurrency');
        $instanceId = $this->option('instance-id');

        // Configuration
        $sshConfig = config('polydock.service_providers_singletons.PolydockServiceProviderFTLagoon', []);
        $sshHost = $sshConfig['ssh_server'] ?? 'ssh.lagoon.amazeeio.cloud';
        $sshPort = $sshConfig['ssh_port'] ?? '32222';
        $globalKeyFile = $sshConfig['ssh_private_key_file'] ?? null;
        $apiEndpoint = $sshConfig['endpoint'] ?? 'https://api.lagoon.amazeeio.cloud/graphql';

        $clientConfig = [
            'ssh_user' => $sshConfig['ssh_user'] ?? 'lagoon',
            'ssh_server' => $sshHost,
            'ssh_port' => $sshPort,
            'endpoint' => $apiEndpoint,
            'ssh_private_key_file' => $globalKeyFile,
        ];

        // --- Single Instance Mode (Worker) ---
        if ($instanceId) {
            $instance = PolydockAppInstance::find($instanceId);
            if (! $instance) {
                $this->error("Instance ID {$instanceId} not found.");

                return 1;
            }

            return $this->deployToInstance($instance, $clientConfig, null, $envOverride, $variablesOnly);
        }

        // --- Bulk Mode (Coordinator) ---

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
                label: 'Select instances to trigger deploy on:',
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

            if (! $this->confirm("Are you sure you want to trigger deployments on {$count} selected instances?")) {
                $this->info('Operation cancelled.');

                return 0;
            }

            if (! $variablesOnly) {
                $variablesOnly = $this->confirm('Do you want to run a variables-only deployment?', false);
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

        // Concurrency Logic
        if ($concurrency > 1) {
            $this->info("Running deployments concurrently on {$count} instances (concurrency: {$concurrency})...");

            $phpBinary = PHP_BINARY;
            $artisan = base_path('artisan');
            $commandBase = [
                $phpBinary,
                $artisan,
                'polydock:instances:run-lagoon-command',
                $appUuid,
                '--force',
            ];

            if ($envOverride) {
                $commandBase[] = "--environment={$envOverride}";
            }
            if ($variablesOnly) {
                $commandBase[] = '--variables-only';
            }

            $pool = Process::pool(function (Pool $pool) use ($instances, $commandBase) {
                foreach ($instances as $instance) {
                    $command = array_merge($commandBase, ["--instance-id={$instance->id}"]);
                    $pool->as($instance->id)->command($command);
                }
            });

            try {
                $pool->concurrency($concurrency);
            } catch (\Throwable) {
                // Ignore if method doesn't exist
            }

            $poolResults = $pool->wait();

            foreach ($poolResults as $instanceId => $result) {
                $instance = $instances->find($instanceId);
                if (! $instance) {
                    continue;
                }

                $projectName = $instance->getKeyValue('lagoon-project-name');

                if ($result->successful()) {
                    // Output already contains "SUCCESS" or "FAILED" messages from child process
                    $this->output->write($result->output());
                } else {
                    $this->error("\n[FAILED] {$projectName} (Process Error): ".trim((string) $result->errorOutput()));
                }
            }

            $this->info('Done.');

            return 0;
        }

        // Serial Logic
        $this->info('Authenticating with Lagoon (Serial Mode)...');
        try {
            if (! $clientConfig['ssh_private_key_file'] || ! file_exists($clientConfig['ssh_private_key_file'])) {
                $this->error('Global SSH private key not found or not configured.');

                return 1;
            }

            $token = $this->getLagoonToken($clientConfig);
            if (empty($token)) {
                $this->error('Failed to retrieve Lagoon API token.');

                return 1;
            }

            if (app()->bound(Client::class)) {
                $client = app(Client::class);
            } else {
                $client = app()->makeWith(Client::class, ['config' => $clientConfig]);
            }

            $client->setLagoonToken($token);
            $client->initGraphqlClient();

        } catch (\Exception $e) {
            $this->error("Authentication failed: {$e->getMessage()}");

            return 1;
        }

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        foreach ($instances as $instance) {
            $this->deployToInstance($instance, $clientConfig, $client, $envOverride, $variablesOnly);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('Done.');

        return 0;
    }

    protected function deployToInstance(
        PolydockAppInstance $instance,
        array $clientConfig,
        ?Client $client = null,
        ?string $envOverride = null,
        bool $variablesOnly = false
    ): int {
        $projectName = $instance->getKeyValue('lagoon-project-name');
        $branch = $envOverride ?: $instance->getKeyValue('lagoon-deploy-branch');

        if (empty($projectName) || empty($branch)) {
            $this->error("\nMissing project name or branch for instance {$instance->id} ({$instance->name})");

            return 1;
        }

        // If client is not provided (e.g. concurrent/child mode), authenticate now
        if (! $client) {
            try {
                if (! $clientConfig['ssh_private_key_file'] || ! file_exists($clientConfig['ssh_private_key_file'])) {
                    $this->error('Global SSH private key not found.');

                    return 1;
                }

                $token = $this->getLagoonToken($clientConfig);
                if (empty($token)) {
                    $this->error("Failed to retrieve Lagoon API token for instance {$instance->id}.");

                    return 1;
                }

                if (app()->bound(Client::class)) {
                    $client = app(Client::class);
                } else {
                    $client = app()->makeWith(Client::class, ['config' => $clientConfig]);
                }

                $client->setLagoonToken($token);
                $client->initGraphqlClient();
            } catch (\Exception $e) {
                $this->error("Authentication failed for instance {$instance->id}: {$e->getMessage()}");

                return 1;
            }
        }

        $buildVars = [];
        if ($variablesOnly) {
            $buildVars['LAGOON_VARIABLES_ONLY'] = 'true';
        }

        try {
            $result = $client->deployProjectEnvironmentByName($projectName, $branch, $buildVars);

            if (isset($result['error'])) {
                $errors = is_array($result['error']) ? json_encode($result['error']) : $result['error'];
                $this->error("\n[FAILED] {$projectName}: {$errors}");

                return 1;
            } else {
                // In concurrent mode, we want to output to stdout so the parent can see it
                // In serial mode, we might want to be quieter or use the progress bar, but for now verbose is fine
                $this->info("\n[SUCCESS] {$projectName}: Deployment triggered.");

                return 0;
            }
        } catch (\Exception $e) {
            $this->error("\n[FAILED] {$projectName}: {$e->getMessage()}");

            return 1;
        }
    }

    protected function getLagoonToken(array $config): string
    {
        if (app()->bound('polydock.lagoon.token_fetcher')) {
            return app('polydock.lagoon.token_fetcher')($config);
        }

        $ssh = Ssh::createLagoonConfigured(
            $config['ssh_user'],
            $config['ssh_server'],
            $config['ssh_port'],
            $config['ssh_private_key_file']
        );

        return $ssh->executeLagoonGetToken();
    }
}
