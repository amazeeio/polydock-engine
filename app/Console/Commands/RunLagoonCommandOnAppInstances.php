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
                            {command_name : The command to run (e.g. "drush cr")}
                            {--environment= : Optional environment override}
                            {--force : Force execution without confirmation}
                            {--service=cli : Service to run the command on (default: cli)}
                            {--container=cli : Container to run the command on (default: cli)}
                            {--concurrency=1 : Number of concurrent processes to run (default: 1 for serial execution)}
                            {--instance-id= : (Internal) Run for a specific instance ID}';

    /**
     * Allowed commands list
     */
    protected array $allowedCommands = [
        'drush cr',
        './script/refresh.sh',
        // Add more allowed commands here
    ];

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run a command via Lagoon API on all running instances of a specific app';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $appUuid = $this->argument(key: 'app_uuid');
        $commandName = $this->argument(key: 'command_name');
        $envOverride = $this->option(key: 'environment');
        $serviceName = $this->option(key: 'service');
        $containerName = $this->option(key: 'container');
        $concurrency = (int) $this->option(key: 'concurrency');
        $instanceId = $this->option(key: 'instance-id');

        if (! in_array(needle: $commandName, haystack: $this->allowedCommands)) {
            $this->error(string: "Command '{$commandName}' is not in the allowed list.");

            return 1;
        }

        // Configuration
        $sshConfig = config(key: 'polydock.service_providers_singletons.PolydockServiceProviderFTLagoon', default: []);
        $sshUser = $sshConfig['ssh_user'] ?? 'lagoon';
        $sshHost = $sshConfig['ssh_server'] ?? 'ssh.lagoon.amazeeio.cloud';
        $sshPort = $sshConfig['ssh_port'] ?? '32222';
        $globalKeyFile = $sshConfig['ssh_private_key_file'] ?? getenv(name: 'HOME').'/.ssh/id_rsa';
        $apiEndpoint = $sshConfig['endpoint'] ?? 'https://api.lagoon.amazeeio.cloud/graphql';

        $clientConfig = [
            'ssh_user' => $sshUser,
            'ssh_server' => $sshHost,
            'ssh_port' => $sshPort,
            'endpoint' => $apiEndpoint,
            'ssh_private_key_file' => $globalKeyFile,
        ];

        // --- Single Instance Mode (Worker) ---
        if ($instanceId) {
            $instance = PolydockAppInstance::find(id: $instanceId);
            if (! $instance) {
                $this->error(string: "Instance ID {$instanceId} not found.");

                return 1;
            }

            return $this->runCommandOnInstance(
                instance: $instance,
                clientConfig: $clientConfig,
                command: $commandName,
                client: null,
                envOverride: $envOverride,
                serviceName: $serviceName,
                containerName: $containerName
            );
        }

        // --- Bulk Mode (Coordinator) ---

        $storeApp = PolydockStoreApp::where(column: 'uuid', operator: '=', value: $appUuid)->first();
        if (! $storeApp) {
            $this->error(string: "Store App with UUID {$appUuid} not found.");

            return 1;
        }

        $this->info(string: "Found Store App: {$storeApp->name} (ID: {$storeApp->id})");

        // Get running instances
        $instances = PolydockAppInstance::where(column: 'polydock_store_app_id', operator: '=', value: $storeApp->id)
            ->whereIn(column: 'status', values: PolydockAppInstance::$stageRunningStatuses)
            ->get();

        $count = $instances->count();
        if ($count === 0) {
            $this->info(string: 'No running instances found for this app.');

            return 0;
        }

        $this->info(string: "Found {$count} running instances.");

        if (! $this->option(key: 'force')) {
            // Pre-calculate column widths
            $maxWidths = [
                'id' => strlen(string: 'ID'),
                'name' => strlen(string: 'Name'),
                'project' => strlen(string: 'Lagoon Project'),
                'branch' => strlen(string: 'Branch'),
            ];

            $instanceData = [];

            foreach ($instances as $instance) {
                $projectName = $instance->getKeyValue(key: 'lagoon-project-name');
                $branch = $envOverride ?: $instance->getKeyValue(key: 'lagoon-deploy-branch');

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
                $this->info(string: 'No instances selected.');

                return 0;
            }

            // Filter instances to only those selected
            $instances = $instances->whereIn(key: 'id', values: $selectedIds);
            $count = $instances->count();

            if (! $this->confirm(question: "Are you sure you want to run '{$commandName}' on {$count} selected instances?")) {
                $this->info(string: 'Operation cancelled.');

                return 0;
            }
        } else {
            // Force mode: show table for audit/info purposes
            $headers = ['ID', 'Name', 'Lagoon Project', 'Branch'];
            $rows = [];

            foreach ($instances as $instance) {
                $projectName = $instance->getKeyValue(key: 'lagoon-project-name');
                $branch = $envOverride ?: $instance->getKeyValue(key: 'lagoon-deploy-branch');
                $rows[] = [$instance->id, $instance->name, $projectName, $branch];
            }

            $this->table(headers: $headers, rows: $rows);
        }

        // Concurrency Logic
        if ($concurrency > 1) {
            $this->info(string: "Running deployments concurrently on {$count} instances (concurrency: {$concurrency})...");

            $phpBinary = PHP_BINARY;
            $artisan = base_path('artisan');
            $commandBase = [
                $phpBinary,
                $artisan,
                'polydock:instances:run-lagoon-command',
                $appUuid,
                $commandName,
                '--force',
            ];

            if ($envOverride) {
                $commandBase[] = "--environment={$envOverride}";
            }
            if ($serviceName !== 'cli') {
                $commandBase[] = "--service={$serviceName}";
            }
            if ($containerName !== 'cli') {
                $commandBase[] = "--container={$containerName}";
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

                $projectName = $instance->getKeyValue(key: 'lagoon-project-name');

                if ($result->successful()) {
                    // Output already contains "SUCCESS" or "FAILED" messages from child process
                    $this->output->write(messages: $result->output());
                } else {
                    $this->error(string: "\n[FAILED] {$projectName} (Process Error): ".trim((string) $result->errorOutput()));
                }
            }

            $this->info(string: 'Done.');

            return 0;
        }

        // Serial Logic
        $this->info(string: 'Authenticating with Lagoon (Serial Mode)...');
        try {
            if (! $clientConfig['ssh_private_key_file'] || ! file_exists(filename: $clientConfig['ssh_private_key_file'])) {
                $this->error(string: 'Global SSH private key not found or not configured.');

                return 1;
            }

            $token = $this->getLagoonToken(config: $clientConfig);
            if (empty($token)) {
                $this->error(string: 'Failed to retrieve Lagoon API token.');

                return 1;
            }

            if (app()->bound(abstract: Client::class)) {
                $client = app(abstract: Client::class);
            } else {
                $client = app()->makeWith(abstract: Client::class, parameters: ['config' => $clientConfig]);
            }

            $client->setLagoonToken($token);
            $client->initGraphqlClient();

        } catch (\Exception $e) {
            $this->error(string: "Authentication failed: {$e->getMessage()}");

            return 1;
        }

        $bar = $this->output->createProgressBar(max: $count);
        $bar->start();

        /** @var \App\Models\PolydockAppInstance $instance */
        foreach ($instances as $instance) {
            $this->runCommandOnInstance(
                instance: $instance,
                clientConfig: $clientConfig,
                command: $commandName,
                client: $client,
                envOverride: $envOverride,
                serviceName: $serviceName,
                containerName: $containerName
            );
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info(string: 'Done.');

        return 0;
    }

    protected function runCommandOnInstance(
        PolydockAppInstance $instance,
        array $clientConfig,
        string $command,
        ?Client $client = null,
        ?string $envOverride = null,
        string $serviceName = 'cli',
        string $containerName = 'cli'
    ): int {
        $projectName = $instance->getKeyValue(key: 'lagoon-project-name');
        $environmentName = $envOverride ?: $instance->getKeyValue(key: 'lagoon-deploy-branch');

        if (empty($projectName) || empty($environmentName)) {
            $this->error(string: "\nMissing project name or branch for instance {$instance->id} ({$instance->name})");

            return 1;
        }

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
                $this->error(string: "Authentication failed for instance {$instance->id}: {$e->getMessage()}");

                return 1;
            }
        }

        try {
            $result = $client->executeCommandOnProjectEnvironment(
                projectName: $projectName,
                environmentName: $environmentName,
                command: $command,
                serviceName: $serviceName,
                containerName: $containerName
            );

            if (isset($result['error'])) {
                $errors = is_array($result['error']) ? json_encode(value: $result['error']) : $result['error'];
                $this->error(string: "\n[FAILED] {$projectName}: {$errors}");

                return 1;
            } else {
                $this->info(string: "\n[SUCCESS] {$projectName}: Command executed successfully.");

                return 0;
            }
        } catch (\Exception $e) {
            $this->error(string: "\n[FAILED] {$projectName}: {$e->getMessage()}");

            return 1;
        }
    }

    protected function getLagoonToken(array $config): string
    {
        if (app()->bound(abstract: 'polydock.lagoon.token_fetcher')) {
            return app(abstract: 'polydock.lagoon.token_fetcher')($config);
        }

        $ssh = Ssh::createLagoonConfigured(
            user: $config['ssh_user'],
            server: $config['ssh_server'],
            port: $config['ssh_port'],
            privateKeyFile: $config['ssh_private_key_file']
        );

        return $ssh->executeLagoonGetToken();
    }
}
