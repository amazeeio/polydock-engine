<?php

namespace App\Console\Commands;

use App\Models\PolydockAppInstance;
use App\Models\PolydockStoreApp;
use App\Services\LagoonClientService;
use FreedomtechHosting\FtLagoonPhp\Client;
use Illuminate\Console\Command;
use Illuminate\Process\Pool;
use Illuminate\Support\Facades\Process;

use function Laravel\Prompts\multiselect;

class TriggerLagoonDeployOnAppInstances extends Command
{
    protected $signature = 'polydock:instances:trigger-deploy
                            {app_uuid : The UUID of the store app}
                            {--environment= : Optional environment override}
                            {--force : Force execution without confirmation}
                            {--variables-only : Only deploy variables}
                            {--concurrency=1 : Number of concurrent processes to run (default: 1 for serial execution)}';

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
        $appUuid = $this->argument(key: 'app_uuid');
        $envOverride = $this->option(key: 'environment');
        $variablesOnly = $this->option(key: 'variables-only');
        $concurrency = max(1, (int) $this->option(key: 'concurrency'));

        /** @var PolydockStoreApp $storeApp */
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

            /** @var PolydockAppInstance $instance */
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

            if (! $this->confirm(question: "Are you sure you want to trigger deployments on {$count} selected instances?")) {
                $this->info(string: 'Operation cancelled.');

                return 0;
            }

            if (! $variablesOnly) {
                $variablesOnly = $this->confirm(question: 'Do you want to run a variables-only deployment?', default: false);
            }
        } else {
            // Force mode: show table for audit/info purposes
            $headers = ['ID', 'Name', 'Lagoon Project', 'Branch'];
            $rows = [];

            /** @var PolydockAppInstance $instance */
            foreach ($instances as $instance) {
                $projectName = $instance->getKeyValue(key: 'lagoon-project-name');
                $branch = $envOverride ?: $instance->getKeyValue(key: 'lagoon-deploy-branch');
                $rows[] = [$instance->id, $instance->name, $projectName, $branch];
            }

            $this->table(headers: $headers, rows: $rows);
        }

        if ($concurrency > 1) {
            $this->info(string: "Running deployments concurrently on {$count} instances (concurrency: {$concurrency})...");

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
            $client = app(LagoonClientService::class)->getAuthenticatedClient();
        } catch (\Exception $e) {
            $this->error(string: "Authentication failed: {$e->getMessage()}");

            return 1;
        }

        $bar = $this->output->createProgressBar(max: $count);
        $bar->start();

        /** @var \App\Models\PolydockAppInstance $instance */
        foreach ($instances as $instance) {
            $this->deployToInstance(
                instance: $instance,
                client: $client,
                envOverride: $envOverride,
                variablesOnly: $variablesOnly
            );
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info(string: 'Done.');

        return 0;
    }

    protected function deployToInstance(
        PolydockAppInstance $instance,
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

        if (! $client) {
            try {
                $client = app(LagoonClientService::class)->getAuthenticatedClient();
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
            $result = $client->deployProjectEnvironmentByName(
                projectName: $projectName,
                deployBranch: $branch,
                buildVariables: $buildVars
            );

            if (isset($result['error'])) {
                $errors = is_array($result['error']) ? json_encode(value: $result['error']) : $result['error'];
                $this->error(string: "\n[FAILED] {$projectName}: {$errors}");

                return 1;
            } else {
                $this->info(string: "\n[SUCCESS] {$projectName}: Deployment triggered.");

                return 0;
            }
        } catch (\Exception $e) {
            $this->error(string: "\n[FAILED] {$projectName}: {$e->getMessage()}");

            return 1;
        }
    }
}
