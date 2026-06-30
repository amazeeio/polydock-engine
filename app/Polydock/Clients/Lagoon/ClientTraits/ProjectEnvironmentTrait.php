<?php

namespace App\Polydock\Clients\Lagoon\ClientTraits;

use App\Polydock\Clients\Lagoon\LagoonClientInitializeRequiredToInteractException;
use App\Polydock\Clients\Lagoon\Ssh;

trait ProjectEnvironmentTrait
{
    /**
     * Checks if a project environment exists by name
     *
     * @param  string  $projectName  The name of the project
     * @param  string  $environmentName  The name of the environment
     * @return bool True if environment exists, false otherwise
     */
    public function projectEnvironmentExistsByName(string $projectName, string $environmentName): bool
    {
        $data = $this->getProjectEnvironmentsByName($projectName);

        return isset($data[$environmentName]);
    }

    /**
     * Gets a specific project environment by name
     *
     * @param  string  $projectName  The name of the project
     * @param  string  $environmentName  The name of the environment
     * @return array Environment data or empty array if not found
     */
    public function getProjectEnvironmentByName(string $projectName, $environmentName): array
    {
        $data = $this->getProjectEnvironmentsByName($projectName);

        return $data[$environmentName] ?? [];
    }

    /**
     * Gets all environments for a project
     *
     * @param  string  $projectName  The name of the project
     * @return array Associative array of environments keyed by name
     *
     * @throws LagoonClientInitializeRequiredToInteractException
     */
    public function getProjectEnvironmentsByName(string $projectName): array
    {
        $data = $this->getProjectByName($projectName);
        $environments = $data['projectByName']['environments'];
        $retenvs = [];

        foreach ($environments as $environment) {
            $retenvs[$environment['name']] = $environment;
        }

        return $retenvs;
    }

    /**
     * Triggers a deployment for a project environment
     *
     * @param  string  $projectName  The name of the project
     * @param  string  $deployBranch  The branch to deploy
     * @param  array  $buildVariables  Optional build variables
     * @return array Response from the API
     *
     * @throws LagoonClientInitializeRequiredToInteractException if client not initialized
     */
    public function deployProjectEnvironmentByName(
        string $projectName,
        string $deployBranch,
        array $buildVariables = []
    ): array {
        if (empty($this->lagoonToken) || empty($this->graphqlClient)) {
            throw new LagoonClientInitializeRequiredToInteractException;
        }

        $input = [
            'project' => ['name' => $projectName],
            'branchName' => $deployBranch,
            'returnData' => true,
        ];

        if (! empty($buildVariables)) {
            $formattedVars = [];
            foreach ($buildVariables as $key => $value) {
                $formattedVars[] = [
                    'name' => $key,
                    'value' => $value,
                ];
            }
            $input['buildVariables'] = $formattedVars;
        }

        $mutation = <<<'GQL'
            mutation m ($input: DeployEnvironmentBranchInput!) {
                deployEnvironmentBranch(input: $input)
            }
        GQL;

        $response = $this->graphqlClient->query($mutation, ['input' => $input]);

        if ($response->hasErrors()) {
            return ['error' => $response->getErrors()];
        } else {
            // Returns an array with all the data returned by the GraphQL server.
            $data = $response->getData();

            return $data;
        }
    }

    /**
     * Triggers a bulk deployment for multiple environments
     *
     * @param  array  $environments  Array of environments to deploy. Each can be an environment ID (int) or an array with 'id' or 'project' and 'name'
     * @param  string|null  $name  Optional name for the bulk deployment
     * @param  array  $buildVariables  Optional build variables to apply to all deployments in the batch
     * @return array Response from the API including the bulk deployment ID
     *
     * @throws LagoonClientInitializeRequiredToInteractException if client not initialized
     */
    public function bulkDeployEnvironments(
        array $environments,
        ?string $name = null,
        array $buildVariables = []
    ): array {
        if (empty($this->lagoonToken) || empty($this->graphqlClient)) {
            throw new LagoonClientInitializeRequiredToInteractException;
        }

        $bulkName = $name ?? 'Bulk deployment '.date('Y-m-d H:i:s');

        $envInputs = [];
        foreach ($environments as $env) {
            if (is_numeric($env)) {
                $envInputs[] = [
                    'environment' => [
                        'id' => (int) $env,
                    ],
                ];
            } elseif (is_array($env)) {
                if (isset($env['id'])) {
                    $envInputs[] = [
                        'environment' => [
                            'id' => (int) $env['id'],
                        ],
                    ];
                } elseif (isset($env['project']) && isset($env['name'])) {
                    $envInputs[] = [
                        'environment' => [
                            'name' => $env['name'],
                            'project' => [
                                'name' => $env['project'],
                            ],
                        ],
                    ];
                }
            }
        }

        $input = [
            'name' => $bulkName,
            'environments' => $envInputs,
        ];

        if (! empty($buildVariables)) {
            $formattedVars = [];
            foreach ($buildVariables as $key => $value) {
                $formattedVars[] = [
                    'name' => $key,
                    'value' => $value,
                ];
            }
            $input['buildVariables'] = $formattedVars;
        }

        $mutation = <<<'GQL'
            mutation m ($input: BulkDeployEnvironmentLatestInput!) {
                bulkDeployEnvironmentLatest(input: $input)
            }
        GQL;

        $response = $this->graphqlClient->query($mutation, ['input' => $input]);

        if ($response->hasErrors()) {
            return ['error' => $response->getErrors()];
        } else {
            $data = $response->getData();

            return $data;
        }
    }

    /**
     * Gets deployments for a specific bulk ID
     *
     * @param  string  $bulkId  The bulk deployment ID
     * @return array Deployment information or error details
     *
     * @throws LagoonClientInitializeRequiredToInteractException if client not initialized
     */
    public function getDeploymentsByBulkId(string $bulkId): array
    {
        if (empty($this->lagoonToken) || empty($this->graphqlClient)) {
            throw new LagoonClientInitializeRequiredToInteractException;
        }

        $query = <<<'GQL'
            query q ($bulkId: String!) {
                deploymentsByBulkId(bulkId: $bulkId) {
                    id
                    name
                    status
                    created
                    started
                    completed
                    environment {
                        name
                        project {
                            name
                        }
                    }
                }
            }
        GQL;

        $response = $this->graphqlClient->query($query, ['bulkId' => $bulkId]);

        if ($response->hasErrors()) {
            return ['error' => $response->getErrors()];
        } else {
            $data = $response->getData();

            return $data['deploymentsByBulkId'] ?? [];
        }
    }

    /**
     * Gets deployment information for a specific deployment
     *
     * @param  string  $projectId  The project ID
     * @param  string  $environmentName  The environment name
     * @param  string  $deploymentName  The deployment name
     * @return array Deployment information or error details
     *
     * @throws LagoonClientInitializeRequiredToInteractException if client not initialized
     */
    public function getProjectDeploymentByProjectIdDeploymentName(string $projectId, string $environmentName, string $deploymentName): array
    {
        if (empty($this->lagoonToken) || empty($this->graphqlClient)) {
            throw new LagoonClientInitializeRequiredToInteractException;
        }

        /**
         * Query Example
         */
        $query = <<<'GQL'
            query q ($project: Int!, $name: String!, $deploymentName: String!) {
                environmentByName(project: $project, name: $name) {
                    deployments(name: $deploymentName) {
                        id
                        remoteId
                        name
                        status
                        created
                        started
                        completed
                    }
                }
            }
        GQL;

        $response = $this->graphqlClient->query($query, [
            'project' => (int) $projectId,
            'name' => $environmentName,
            'deploymentName' => $deploymentName,
        ]);

        if ($response->hasErrors()) {
            return ['error' => $response->getErrors()];
        } else {
            // Returns an array with all the data returned by the GraphQL server.
            $data = $response->getData();

            return $data['environmentByName']['deployments'][0] ?? ['error' => 'Deployment not found: '.$deploymentName, 'errorData' => $data];
        }
    }

    /**
     * Gets deployments for a project environment
     *
     * @param  string  $projectName  The name of the project
     * @param  string|null  $environmentName  The name of the environment
     * @return array Deployment information or error details
     *
     * @throws LagoonClientInitializeRequiredToInteractException if client not initialized
     */
    public function getProjectEnvironmentDeployments(string $projectName, ?string $environmentName = null): array
    {
        if (empty($this->lagoonToken) || empty($this->graphqlClient)) {
            throw new LagoonClientInitializeRequiredToInteractException;
        }

        $query = <<<'GQL'
            query q ($name: String!) {
                projectByName(name: $name) {
                    environments {
                        name
                        deployments {
                            id
                            name
                            priority
                            buildStep
                            status
                            started
                            completed
                        }
                    }
                }
            }
        GQL;

        $response = $this->graphqlClient->query($query, ['name' => $projectName]);

        /***
         * Example Response
         * {
            "data": {
                "projectByName": {
                "environments": [
                    {
                    "name": "main",
                    "deployments": [
                        {
                        "id": 5269,
                        "name": "lagoon-build-u9izs5",
                        "priority": null,
                        "buildStep": null,
                        "status": "new",
                        "started": null,
                        "completed": null
                        }
                    ]
                    }
                ]
                }
            }
            }
         */

        if ($response->hasErrors()) {
            return ['error' => $response->getErrors()];
        }

        $data = $response->getData();
        $deployments = [];

        if (isset($data['projectByName']['environments'])) {
            foreach ($data['projectByName']['environments'] as $environment) {
                $envName = $environment['name'];
                if (! empty($environment['deployments'])) {
                    $deployments[$envName] = $environment['deployments'];
                }
            }
        }

        if (! empty($environmentName)) {
            return isset($deployments[$environmentName]) ? [$environmentName => $deployments[$environmentName]] : [];
        }

        return $deployments;
    }

    /**
     * Deletes a project environment
     *
     * @param  string  $projectName  The name of the project
     * @param  string  $environmentName  The name of the environment to delete
     * @return array Response from the API
     *
     * @throws LagoonClientInitializeRequiredToInteractException if client not initialized
     */
    public function deleteProjectEnvironmentByName(
        string $projectName,
        string $environmentName,
    ): array {
        if (empty($this->lagoonToken) || empty($this->graphqlClient)) {
            throw new LagoonClientInitializeRequiredToInteractException;
        }

        $mutation = <<<'GQL'
            mutation m ($input: DeleteEnvironmentInput!) {
                deleteEnvironment(input: $input)
            }
        GQL;

        $response = $this->graphqlClient->query($mutation, [
            'input' => [
                'project' => $projectName,
                'name' => $environmentName,
                'execute' => true,
            ],
        ]);

        if ($response->hasErrors()) {
            return ['error' => $response->getErrors()];
        } else {
            // Returns an array with all the data returned by the GraphQL server.
            $data = $response->getData();

            return $data;
        }
    }

    /**
     * Adds or updates a variable with a specific scope for a project environment
     *
     * @param  string  $projectName  The name of the project
     * @param  string  $environmentName  The name of the environment
     * @param  string  $key  The variable key/name
     * @param  string  $value  The variable value
     * @param  string  $scope  The scope of the variable (GLOBAL, RUNTIME, BUILD, CONTAINER_REGISTRY)
     * @return array Response from the API
     *
     * @throws LagoonClientInitializeRequiredToInteractException if client not initialized
     */
    public function addOrUpdateScopedVariableForProjectEnvironment(
        string $projectName,
        string $environmentName,
        string $key,
        string $value,
        string $scope
    ): array {
        return $this->addOrUpdateScopedVariableForProject(
            $projectName,
            $key,
            $value,
            $scope,
            $environmentName
        );
    }

    /**
     * Gets all variables for a specific project environment
     *
     * @param  string  $projectName  The name of the project
     * @param  string  $environmentName  The name of the environment
     * @return array Associative array of variables with their values and scopes
     *
     * @throws LagoonClientInitializeRequiredToInteractException
     */
    public function getProjectVariablesByNameForEnvironment(string $projectName, string $environmentName): array
    {
        $data = $this->getProjectByName($projectName);
        $environments = $data['projectByName']['environments'] ?? [];
        $retvars = [];

        foreach ($environments as $environment) {
            if ($environment['name'] === $environmentName) {
                $lagoonVars = $environment['envVariables'] ?? [];
                foreach ($lagoonVars as $lagoonVar) {
                    $retvars[$lagoonVar['name']] = [
                        'value' => $lagoonVar['value'],
                        'scope' => $lagoonVar['scope'],
                    ];
                }
                break;
            }
        }

        return $retvars;
    }

    /**
     * Gets a specific variable for a specific project environment
     *
     * @param  string  $projectName  The name of the project
     * @param  string  $environmentName  The name of the environment
     * @param  string  $variableName  The name of the variable to retrieve
     * @return array Variable data including value and scope, or empty array if not found
     *
     * @throws LagoonClientInitializeRequiredToInteractException
     */
    public function getProjectVariableByNameForEnvironment(string $projectName, string $environmentName, string $variableName): array
    {
        $variables = $this->getProjectVariablesByNameForEnvironment($projectName, $environmentName);

        return $variables[$variableName] ?? [];
    }

    /**
     * Deletes a variable from a project environment
     *
     * @param  string  $projectName  The name of the project
     * @param  string  $variableName  The name of the variable to delete
     * @param  string  $environmentName  The name of the environment
     * @return array Response from the API
     *
     * @throws LagoonClientInitializeRequiredToInteractException if client not initialized
     */
    public function deleteProjectVariableByNameForEnvironment(
        string $projectName,
        string $variableName,
        string $environmentName
    ) {
        return $this->deleteProjectVariableByName(
            $projectName,
            $variableName,
            $environmentName
        );
    }

    public function executeCommandOnProjectEnvironment(
        string $projectName,
        string $environmentName,
        string $command,
        string $serviceName = 'cli',
        string $containerName = 'cli',
        ?string $input = null
    ): array {
        if ($this->getDebug()) {
            echo "Executing command on project environment: {$projectName} {$environmentName} {$command}\n";
        }

        $projectEnvironmentUser = "{$projectName}-{$environmentName}";

        $ssh = Ssh::createLagoonConfigured(
            $projectEnvironmentUser,
            $this->lagoonSshServer,
            $this->lagoonSshPort,
            $this->sshPrivateKeyFile
        );

        $result = $ssh->executeSShCommand($command, $serviceName, $containerName, $input);

        if ($this->getDebug()) {
            echo "Command Result:\n----\n";
            print_r($result);
            echo "\n----\n";
        }

        return $result;
    }
}
