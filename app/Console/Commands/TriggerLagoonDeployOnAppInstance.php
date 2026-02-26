<?php

namespace App\Console\Commands;

use App\Models\PolydockAppInstance;
use FreedomtechHosting\FtLagoonPhp\Client;
use FreedomtechHosting\FtLagoonPhp\Ssh;
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
            clientConfig: $clientConfig,
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
        array $clientConfig,
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
                if (! $clientConfig['ssh_private_key_file'] || ! file_exists(filename: $clientConfig['ssh_private_key_file'])) {
                    $this->error(string: 'Global SSH private key not found.');

                    return 1;
                }

                $token = $this->getLagoonToken(config: $clientConfig);
                if (empty($token)) {
                    $this->error(string: "Failed to retrieve Lagoon API token for instance {$instance->id}.");

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
