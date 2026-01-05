<?php

namespace App\Console\Commands;

use App\Models\PolydockAppInstance;
use App\Models\PolydockStoreApp;
use Illuminate\Console\Command;
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
                            {--force : Force execution without confirmation}';

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
                    $maxWidths[$key] = max($width, strlen($data[$key]));
                }
            }

            // Create formatted options
            $options = [];
            foreach ($instanceData as $id => $data) {
                $label = sprintf(
                    '%s  %s  %s  %s',
                    str_pad($data['id'], $maxWidths['id']),
                    str_pad($data['name'], $maxWidths['name']),
                    str_pad($data['project'], $maxWidths['project']),
                    str_pad($data['branch'], $maxWidths['branch'])
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

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        foreach ($instances as $instance) {
            $projectName = $instance->getKeyValue('lagoon-project-name');
            $branch = $envOverride ?: $instance->getKeyValue('lagoon-deploy-branch');

            if (empty($projectName) || empty($branch)) {
                $this->error("\nMissing project name or branch for instance {$instance->id} ({$instance->name})");
                $bar->advance();

                continue;
            }

            // Construct Lagoon CLI command
            // lagoon ssh -p <project> -e <branch> -- <command>
            // We escape the project and branch, but we assume the command is provided as desired.
            // Note: If the command contains quotes, the user should escape them or wrap the whole arg in quotes in the shell.

            $fullCommand = sprintf('lagoon ssh -p %s -e %s -- %s',
                escapeshellarg($projectName),
                escapeshellarg($branch),
                $commandToRun
            );

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
    }
}
