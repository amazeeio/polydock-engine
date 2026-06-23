<?php

namespace App\Polydock\Clients\Lagoon\ClientTraits;

use App\Polydock\Clients\Lagoon\Enums\LagoonVariableScope;
use App\Polydock\Clients\Lagoon\LagoonClientInitializeRequiredToInteractException;
use App\Polydock\Clients\Lagoon\LagoonVariableScopeInvalidException;

trait ProjectTrait
{
    /**
     * Creates a new Lagoon project
     *
     * @param  string  $projectName  The name of the project
     * @param  string  $gitUrl  The Git repository URL
     * @param  string  $branches  The branches to deploy
     * @param  string  $productionEnvironment  The production environment
     * @param  int  $clusterId  The Kubernetes cluster ID
     * @param  string|null  $privateKey  The private key for Git access
     * @param  int|null  $autoIdle  Whether to enable autoIdle (0 = disabled, 1 = enabled)
     * @return array Response from the API
     *
     * @throws LagoonClientInitializeRequiredToInteractException
     */
    public function createLagoonProject(
        string $projectName,
        string $gitUrl,
        string $branches,
        string $productionEnvironment,
        int $clusterId,
        ?string $privateKey = null,
        ?int $autoIdle = 0): array
    {

        $projectInput = [
            'name' => $projectName,
            'gitUrl' => $gitUrl,
            'kubernetes' => $clusterId,
            'branches' => $branches,
            'productionEnvironment' => $productionEnvironment,
            'autoIdle' => $autoIdle ?? 0,
        ] + (! empty($privateKey) ? ['privateKey' => $privateKey] : []);

        return $this->addProjectMutation($projectInput);
    }

    /**
     * Creates a new Lagoon project within an organization
     *
     * @param  string  $projectName  The name of the project
     * @param  string  $gitUrl  The Git repository URL
     * @param  string  $branches  The branches to deploy
     * @param  string  $productionEnvironment  The production environment
     * @param  int  $clusterId  The Kubernetes cluster ID
     * @param  string  $privateKey  The private key for Git access
     * @param  int  $orgId  The organization ID
     * @param  bool  $addOrgOwnerToProject  Whether to add organization owner to project
     * @param  int|null  $autoIdle  Whether to enable autoIdle (0 = disabled, 1 = enabled)
     * @return array Response from the API
     *
     * @throws LagoonClientInitializeRequiredToInteractException
     */
    public function createLagoonProjectInOrganization(
        string $projectName,
        string $gitUrl,
        string $branches,
        string $productionEnvironment,
        int $clusterId,
        string $privateKey,
        int $orgId,
        bool $addOrgOwnerToProject,
        ?int $autoIdle = 0): array
    {

        $projectInput = [
            'name' => $projectName,
            'gitUrl' => $gitUrl,
            'kubernetes' => $clusterId,
            'branches' => $branches,
            'productionEnvironment' => $productionEnvironment,
            'organization' => $orgId,
            'addOrgOwner' => $addOrgOwnerToProject,
            'autoIdle' => $autoIdle ?? 0,
        ];

        if (! empty($privateKey)) {
            $projectInput['privateKey'] = $privateKey;
        }

        return $this->addProjectMutation($projectInput);
    }

    /**
     * Provides a generic runner for explicit addProject implementations
     *
     * @param  array  $addProjectInput  Project configuration array
     * @return array Response from the API
     *
     * @throws LagoonClientInitializeRequiredToInteractException if client not initialized
     */
    protected function addProjectMutation(array $addProjectInput): array
    {

        if (empty($this->lagoonToken) || empty($this->graphqlClient)) {
            throw new LagoonClientInitializeRequiredToInteractException;
        }

        $mutation = <<<'GQL'
            mutation ($projectInput: AddProjectInput!) {
                addProject(input: $projectInput) {
                    id
                    name
                    gitUrl
                    branches
                    productionEnvironment
                }
            }
        GQL;

        $projectInput = [
            'projectInput' => $addProjectInput,
        ];

        $response = $this->graphqlClient->query($mutation, $projectInput);

        if ($response->hasErrors()) {
            return ['error' => $response->getErrors()];
        } else {
            // Returns an array with all the data returned by the GraphQL server.
            return $response->getData();
        }
    }

    /**
     * Checks if a project exists by name
     *
     * @param  string  $projectName  The name of the project to check
     * @return bool True if project exists, false otherwise
     *
     * @throws LagoonClientInitializeRequiredToInteractException
     */
    public function projectExistsByName(string $projectName): bool
    {
        $data = $this->getProjectByName($projectName);

        return isset($data['projectByName']['id']);
    }

    /**
     * Gets detailed information about a project
     *
     * @param  string  $projectName  The name of the project
     * @return array Project data including environments, variables, and metadata
     *
     * @throws LagoonClientInitializeRequiredToInteractException if client not initialized
     */
    public function getProjectByName(string $projectName): array
    {
        if (empty($this->lagoonToken) || empty($this->graphqlClient)) {
            throw new LagoonClientInitializeRequiredToInteractException;
        }

        /**
         * Query Example
         */
        $query = <<<'GQL'
            query q($name: String!) {
                projectByName(name: $name) {
                    id
                    name
                    productionEnvironment
                    branches
                    gitUrl
                    openshift {
                        id
                        name
                        cloudProvider
                        cloudRegion
                    }
                    created
                    metadata
                    envVariables {
                        id
                        name
                        value
                        scope
                    }
                    publicKey
                    privateKey
                    availability
                    environments {
                        id
                        name
                        created
                        updated
                        deleted
                        environmentType
                        route
                        routes
                        envVariables {
                          id
                          name
                          value
                          scope
                        }
                    }
                }
            }
        GQL;

        $response = $this->graphqlClient->query($query, ['name' => $projectName]);

        if ($response->hasErrors()) {
            return ['error' => $response->getErrors()];
        } else {
            // Returns an array with all the data returned by the GraphQL server.
            return $response->getData();
        }
    }

    /**
     * Updates project metadata for a Lagoon project
     *
     * @param  int|string  $projectIdOrName  The ID or name of the project
     * @param  string  $key  The metadata key
     * @param  string  $value  The metadata value
     * @return array Response from the API
     *
     * @throws LagoonClientInitializeRequiredToInteractException if client not initialized
     */
    public function updateProjectMetadata(int|string $projectIdOrName, string $key, string $value): array
    {
        if (empty($this->lagoonToken) || empty($this->graphqlClient)) {
            throw new LagoonClientInitializeRequiredToInteractException;
        }

        $projectId = $projectIdOrName;

        // If a non-numeric string is passed, treat it as a project name and look up the ID.
        if (! is_numeric($projectIdOrName)) {
            $projectData = $this->getProjectByName($projectIdOrName);
            if (empty($projectData['projectByName']['id'])) {
                return ['error' => sprintf('Project "%s" not found.', $projectIdOrName)];
            }
            $projectId = (int) $projectData['projectByName']['id'];
        } else {
            $projectId = (int) $projectIdOrName;
        }

        // Inline parameters exactly like the working GQL example to avoid dependency on GraphQL schema types
        $escapedKey = json_encode($key);
        $escapedValue = json_encode($value);

        $mutation = "
            mutation {
                updateProjectMetadata(
                    input: { id: {$projectId}, patch: { key: {$escapedKey}, value: {$escapedValue} } }
                ) {
                    id
                    metadata
                }
            }
        ";

        $response = $this->graphqlClient->query($mutation);

        if ($response->hasErrors()) {
            return ['error' => $response->getErrors()];
        } else {
            return $response->getData();
        }
    }

    /**
     * Gets all variables for a project
     *
     * @param  string  $projectName  The name of the project
     * @return array Associative array of variables with their values and scopes
     *
     * @throws LagoonClientInitializeRequiredToInteractException
     */
    public function getProjectVariablesByName(string $projectName): array
    {
        $data = $this->getProjectByName($projectName);
        $lagoonVars = $data['projectByName']['envVariables'] ?? [];
        $retvars = [];

        foreach ($lagoonVars as $lagoonVar) {
            $retvars[$lagoonVar['name']] = [
                'value' => $lagoonVar['value'],
                'scope' => $lagoonVar['scope'],
            ];
        }

        return $retvars;
    }

    /**
     * Gets a specific variable for a project by variable name
     *
     * @param  string  $projectName  The name of the project
     * @param  string  $variableName  The name of the variable to retrieve
     * @return array Variable data including value and scope, or empty array if not found
     *
     * @throws LagoonClientInitializeRequiredToInteractException
     */
    public function getProjectVariableByName(string $projectName, string $variableName): array
    {
        $variables = $this->getProjectVariablesByName($projectName);

        return $variables[$variableName] ?? [];
    }

    /**
     * Adds or updates a variable with a specific scope for a project
     *
     * @param  string  $projectName  The name of the project
     * @param  string  $key  The variable key/name
     * @param  string  $value  The variable value
     * @param  string  $scope  The scope of the variable (GLOBAL, RUNTIME, BUILD, CONTAINER_REGISTRY)
     * @param  string|null  $environment  Optional environment name
     * @return array Response from the API
     *
     * @throws LagoonClientInitializeRequiredToInteractException|LagoonVariableScopeInvalidException if client not initialized
     */
    public function addOrUpdateScopedVariableForProject(
        string $projectName,
        string $key,
        string $value,
        string $scope,
        ?string $environment = null
    ): array {
        if (empty($this->lagoonToken) || empty($this->graphqlClient)) {
            throw new LagoonClientInitializeRequiredToInteractException;
        }

        if (LagoonVariableScope::tryFrom($scope) === null) {
            throw new LagoonVariableScopeInvalidException;
        }

        $mutation = <<<'GQL'
            mutation m($input: EnvVariableByNameInput!) {
                addOrUpdateEnvVariableByName(input: $input) {
                    id
                    name
                    value
                    scope
                }
            }
        GQL;

        $input = [
            'project' => $projectName,
            'name' => $key,
            'scope' => $scope,
            'value' => $value,
        ];

        if (! empty($environment)) {
            $input['environment'] = $environment;
        }

        $response = $this->graphqlClient->query($mutation, ['input' => $input]);

        if ($response->hasErrors()) {
            return ['error' => $response->getErrors()];
        } else {
            // Returns an array with all the data returned by the GraphQL server.
            return $response->getData();
        }
    }

    /**
     * Adds or updates a global variable for a project
     *
     * @param  string  $projectName  The name of the project
     * @param  string  $key  The variable key/name
     * @param  string  $value  The variable value
     * @return array Response from the API
     *
     * @throws LagoonClientInitializeRequiredToInteractException|LagoonVariableScopeInvalidException if client not initialized
     */
    public function addOrUpdateGlobalVariableForProject(
        string $projectName,
        string $key,
        string $value
    ) {
        return $this->addOrUpdateScopedVariableForProject($projectName, $key, $value, 'GLOBAL');
    }

    /**
     * Adds or updates a variable with a specific scope for an organization
     *
     * @param  string  $organizationName  The organization name
     * @param  string  $key  The variable key/name
     * @param  string  $value  The variable value
     * @param  string  $scope  The scope of the variable (GLOBAL, RUNTIME, BUILD, CONTAINER_REGISTRY)
     * @return array Response from the API
     *
     * @throws LagoonClientInitializeRequiredToInteractException|LagoonVariableScopeInvalidException if client not initialized
     */
    public function addOrUpdateScopedVariableForOrganization(
        string $organizationName,
        string $key,
        string $value,
        string $scope
    ): array {
        if (empty($this->lagoonToken) || empty($this->graphqlClient)) {
            throw new LagoonClientInitializeRequiredToInteractException;
        }

        if (LagoonVariableScope::tryFrom($scope) === null) {
            throw new LagoonVariableScopeInvalidException;
        }

        $mutation = <<<'GQL'
            mutation m($input: EnvVariableByNameInput!) {
                addOrUpdateEnvVariableByName(input: $input) {
                    id
                    name
                    value
                    scope
                }
            }
        GQL;

        $input = [
            'organization' => (int) $organizationName, // Lagoon expects Int for organization in this mutation if using ID, or String for name?
            'name' => $key,
            'scope' => $scope,
            'value' => $value,
        ];

        $response = $this->graphqlClient->query($mutation, ['input' => $input]);

        if ($response->hasErrors()) {
            return ['error' => $response->getErrors()];
        } else {
            // Returns an array with all the data returned by the GraphQL server.
            return $response->getData();
        }
    }

    /**
     * Adds or updates a global variable for an organization
     *
     * @param  string  $organizationName  The organization name
     * @param  string  $key  The variable key/name
     * @param  string  $value  The variable value
     * @return array Response from the API
     *
     * @throws LagoonClientInitializeRequiredToInteractException|LagoonVariableScopeInvalidException if client not initialized
     */
    public function addOrUpdateGlobalVariableForOrganization(
        string $organizationName,
        string $key,
        string $value
    ) {
        return $this->addOrUpdateScopedVariableForOrganization($organizationName, $key, $value, 'GLOBAL');
    }

    /**
     * Gets all projects from the API
     *
     * @return array Array of all projects and their details
     *
     * @throws LagoonClientInitializeRequiredToInteractException if client not initialized
     */
    public function getAllProjects(): array
    {
        if (empty($this->lagoonToken) || empty($this->graphqlClient)) {
            throw new LagoonClientInitializeRequiredToInteractException;
        }

        $query = <<<'GQL'
            query q {
                allProjects {
                    id
                    name
                    productionEnvironment
                    branches
                    gitUrl
                    openshift {
                        id
                        name
                        cloudProvider
                        cloudRegion
                    }
                    created
                    availability
                    environments {
                        id
                        name
                        created
                        updated
                        deleted
                        environmentType
                        route
                        routes
                    }
                }
            }
        GQL;

        $response = $this->graphqlClient->query($query);

        if ($response->hasErrors()) {
            return ['error' => $response->getErrors()];
        } else {
            return $response->getData();
        }
    }

    /**
     * Deletes a variable from a project or project environment
     *
     * @param  string  $projectName  The name of the project
     * @param  string  $variableName  The name of the variable to delete
     * @param  string|null  $environment  Optional environment name
     * @return array Response from the API
     *
     * @throws LagoonClientInitializeRequiredToInteractException if client not initialized
     */
    public function deleteProjectVariableByName(
        string $projectName,
        string $variableName,
        ?string $environment = null
    ): array {
        if (empty($this->lagoonToken) || empty($this->graphqlClient)) {
            throw new LagoonClientInitializeRequiredToInteractException;
        }

        $mutation = <<<'GQL'
            mutation m($input: DeleteEnvVariableByNameInput!) {
                deleteEnvVariableByName(input: $input)
            }
        GQL;

        $input = [
            'project' => $projectName,
            'name' => $variableName,
        ];

        if (! empty($environment)) {
            $input['environment'] = $environment;
        }

        $response = $this->graphqlClient->query($mutation, ['input' => $input]);

        if ($response->hasErrors()) {
            return ['error' => $response->getErrors()];
        } else {
            // Returns an array with all the data returned by the GraphQL server.
            return $response->getData();
        }
    }

    /**
     * Deletes a project environment
     *
     * @param  string  $projectName  The name of the project
     * @return array Response from the API
     *
     * @throws LagoonClientInitializeRequiredToInteractException if client not initialized
     */
    public function deleteProjectByName(
        string $projectName,
    ): array {
        if (empty($this->lagoonToken) || empty($this->graphqlClient)) {
            throw new LagoonClientInitializeRequiredToInteractException;
        }

        $mutation = <<<GQL
            mutation d {
                deleteProject(input: {
                    project: "{$projectName}"
                })
            }
        GQL;

        $response = $this->graphqlClient->query($mutation);

        if ($response->hasErrors()) {
            return ['error' => $response->getErrors()];
        } else {
            // Returns an array with all the data returned by the GraphQL server.
            return $response->getData();
        }
    }

    /**
     * @throws LagoonClientInitializeRequiredToInteractException
     * @throws \Exception
     */
    public function addProjectDeployTargetByProjectId(int $projectId, int $deployTargetId, int $weight,
        ?string $branches = null, ?string $pullrequests = null): array
    {
        if (empty($this->lagoonToken) || empty($this->graphqlClient)) {
            throw new LagoonClientInitializeRequiredToInteractException;
        }

        if (empty($branches) && empty($pullrequests)) {
            throw new \Exception('At least one of branches or pullrequests must be provided');
        }

        $mutation = <<<'GQL'
            mutation ($input: AddDeployTargetConfigInput!) {
                addDeployTargetConfig(input: $input) {
                    id
                    branches
                    pullrequests
                    weight
                    project {
                        id
                        name
                    }
                    deployTarget {
                        id
                        name
                        friendlyName
                        cloudRegion
                        cloudProvider
                    }
                }
            }
        GQL;

        $input = [
            'project' => $projectId,
            'deployTarget' => $deployTargetId,
            'weight' => $weight,
        ];

        if (! empty($branches)) {
            $input['branches'] = $branches;
        }

        if (! empty($pullrequests)) {
            $input['pullrequests'] = $pullrequests;
        }

        $response = $this->graphqlClient->query($mutation, ['input' => $input]);

        if ($response->hasErrors()) {
            return ['error' => $response->getErrors()];
        } else {
            return $response->getData();
        }
    }

    /**
     * @throws LagoonClientInitializeRequiredToInteractException
     */
    public function getProjectDeployTargetsByProjectId(int $projectId): array
    {
        if (empty($this->lagoonToken) || empty($this->graphqlClient)) {
            throw new LagoonClientInitializeRequiredToInteractException;
        }

        $query = <<<'GQL'
            query ($project: Int!) {
                deployTargetConfigsByProjectId(project: $project) {
                    id
                    branches
                    pullrequests
                    weight
                    project {
                        id
                        name
                    }
                    deployTarget {
                        id
                        name
                        friendlyName
                        cloudRegion
                        cloudProvider
                    }
                }
            }
        GQL;

        $response = $this->graphqlClient->query($query, ['project' => $projectId]);

        if ($response->hasErrors()) {
            return ['error' => $response->getErrors()];
        } else {
            return $response->getData();
        }
    }

    /**
     * @throws LagoonClientInitializeRequiredToInteractException
     */
    public function getProjectDeployTargetByConfigId(int $deployTargetConfigId): array
    {
        if (empty($this->lagoonToken) || empty($this->graphqlClient)) {
            throw new LagoonClientInitializeRequiredToInteractException;
        }

        $query = <<<'GQL'
            query ($id: Int!) {
                deployTargetConfigById(id: $id) {
                    id
                    branches
                    pullrequests
                    weight
                    project {
                        id
                        name
                    }
                    deployTarget {
                        id
                        name
                        friendlyName
                        cloudRegion
                        cloudProvider
                    }
                }
            }
        GQL;

        $response = $this->graphqlClient->query($query, ['id' => $deployTargetConfigId]);

        if ($response->hasErrors()) {
            return ['error' => $response->getErrors()];
        } else {
            return $response->getData();
        }
    }

    /**
     * @throws LagoonClientInitializeRequiredToInteractException
     * @throws \Exception
     */
    public function updateProjectDeployTargetByConfigId(int $deployTargetConfigId, int $deployTargetId, ?int $weight = null,
        ?string $branches = null, ?string $pullRequest = null): array
    {
        if (empty($this->lagoonToken) || empty($this->graphqlClient)) {
            throw new LagoonClientInitializeRequiredToInteractException;
        }

        if (empty($branches) && empty($pullRequest)) {
            throw new \Exception('At least one of branches or pullRequest must be provided');
        }

        $mutation = <<<'GQL'
            mutation ($input: UpdateDeployTargetConfigInput!) {
                updateDeployTargetConfig(input: $input) {
                    id
                    weight
                    branches
                    pullrequests
                    deployTargetProjectPattern
                    deployTarget {
                        id
                        name
                        friendlyName
                        cloudRegion
                        cloudProvider
                    }
                    project {
                        name
                    }
                }
            }
        GQL;

        $patch = [
            'deployTarget' => $deployTargetId,
        ];

        if (! empty($branches)) {
            $patch['branches'] = $branches;
        }

        if (! empty($pullRequest)) {
            $patch['pullrequests'] = $pullRequest;
        }

        if (! is_null($weight)) {
            $patch['weight'] = $weight;
        }

        $input = [
            'id' => $deployTargetConfigId,
            'patch' => $patch,
        ];

        $response = $this->graphqlClient->query($mutation, ['input' => $input]);

        if ($response->hasErrors()) {
            return ['error' => $response->getErrors()];
        } else {
            return $response->getData();
        }
    }

    /**
     * @throws LagoonClientInitializeRequiredToInteractException
     */
    public function deleteProjectDeployTargetByConfigId(int $deployTargetConfigId, int $projectId): array
    {
        if (empty($this->lagoonToken) || empty($this->graphqlClient)) {
            throw new LagoonClientInitializeRequiredToInteractException;
        }

        $mutation = <<<'GQL'
            mutation ($input: DeleteDeployTargetConfigInput!) {
                deleteDeployTargetConfig(input: $input)
            }
        GQL;

        $input = [
            'id' => $deployTargetConfigId,
            'project' => $projectId,
            'execute' => true,
        ];

        $response = $this->graphqlClient->query($mutation, ['input' => $input]);

        if ($response->hasErrors()) {
            return ['error' => $response->getErrors()];
        } else {
            return ['id' => $deployTargetConfigId, 'project' => $projectId, 'execute' => true];
        }
    }
}
