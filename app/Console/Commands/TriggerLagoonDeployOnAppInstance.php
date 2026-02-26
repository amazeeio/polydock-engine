<?php

namespace App\Console\Commands;

use App\Models\PolydockAppInstance;
use App\Services\LagoonClientService;
use FreedomtechHosting\FtLagoonPhp\Client;
use Illuminate\Console\Command;

class TriggerLagoonDeployOnAppInstance extends Command
{
    protected $signature = 'polydock:app-instance:trigger-deploy
                            {instance_uuid : The UUID of the app instance to trigger a deploy on}
                            {--environment= : Optional environment override}
                            {--force : Force execution without confirmation}
                            {--variables-only : Only deploy variables}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Trigger a deployment via Lagoon API on a single running app instance';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $instanceUuid = $this->argument(key: 'instance_uuid');
        $envOverride = $this->option(key: 'environment');
        $variablesOnly = $this->option(key: 'variables-only');

        /** @var PolydockAppInstance|null $instance */
        $instance = PolydockAppInstance::where('uuid', $instanceUuid)->first();
        if (! $instance) {
            $this->error(string: "Instance UUID {$instanceUuid} not found.");

            return 1;
        }

        $this->info(string: "Found App Instance: {$instance->name} (UUID: {$instance->uuid})");

        if (! $this->option(key: 'force')) {
            if (! $variablesOnly) {
                $variablesOnly = $this->confirm(question: 'Do you want to run a variables-only deployment?', default: false);
            }
        }

        $status = $this->deployToInstance(
            instance: $instance,
            client: null,
            envOverride: $envOverride,
            variablesOnly: $variablesOnly
        );

        if ($status === 0) {
            $this->newLine();
            $this->info(string: 'Done.');
        }

        return $status;
    }

    protected function deployToInstance(
        PolydockAppInstance $instance,
        ?Client $client = null,
        ?string $envOverride = null,
        bool $variablesOnly = false
    ): int {
        $projectName = $instance->getKeyValue(key: 'lagoon-project-name');
        $branch = $envOverride ?: $instance->getKeyValue(key: 'lagoon-deploy-branch');

        if (empty($projectName) || empty($branch)) {
            $this->error(string: "\nMissing project name or branch for instance {$instance->id} ({$instance->name})");

            return 1;
        }

        if (! $client) {
            try {
                $client = app(LagoonClientService::class)->getAuthenticatedClient();
            } catch (\Exception $e) {
                $this->error(string: "Authentication failed for instance {$instance->id}: {$e->getMessage()}");

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
