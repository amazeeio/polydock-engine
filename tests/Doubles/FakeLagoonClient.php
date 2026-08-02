<?php

declare(strict_types=1);

namespace Tests\Doubles;

use App\Polydock\Clients\Lagoon\Client;

/**
 * Test double for the Lagoon Client. Records bulk-deploy calls and returns canned
 * responses for deployment status polling. Overrides the constructor to avoid the
 * real client's SSH/config setup.
 */
class FakeLagoonClient extends Client
{
    /** @var array<int, array<string, mixed>> */
    public array $bulkCalls = [];

    public string $bulkId = 'bulk-test-1';

    /** @var array<string, mixed>|null Override the bulk-deploy response entirely. */
    public ?array $bulkResponse = null;

    public bool $throwOnDeploy = false;

    /** @var array<int, array<int, mixed>> Queue of successive getDeploymentsByBulkId responses. */
    public array $deploymentResponses = [];

    /** @var array<int, mixed>|null Last response, repeated once the queue empties. */
    public ?array $lastDeployments = null;

    public bool $throwOnPoll = false;

    /** @var array<string, array<string, mixed>> Canned getProjectByName responses keyed by project name. */
    public array $projects = [];

    /** @var array<int, array{group: string, project: string}> Recorded addGroupToProject calls. */
    public array $groupAdds = [];

    /**
     * @var array<string, mixed>
     */
    public ?array $addGroupResponse = null;

    public function __construct()
    {
        // Intentionally bypass the real Client constructor (SSH/config setup).
    }

    public bool $throwOnGetProject = false;

    /** @var array<int, array{project: string, environment: string}> Recorded environment deletions. */
    public array $environmentDeletes = [];

    /**
     * @var array<string, mixed>
     */
    public ?array $deleteEnvironmentResponse = null;

    /** @var array<int, string> Recorded project deletions. */
    public array $projectDeletes = [];

    /**
     * @var array<string, mixed>
     */
    public ?array $deleteProjectResponse = null;

    /** @var array<int, array{project: string, key: string, value: string}> Recorded metadata writes. */
    public array $metadataWrites = [];

    /**
     * @var array<string, mixed>|null
     */
    public ?array $updateMetadataResponse = null;

    /**
     * @return array<string, mixed>
     */
    #[\Override]
    public function updateProjectMetadata(int|string $projectIdOrName, string $key, string $value): array
    {
        $this->metadataWrites[] = ['project' => (string) $projectIdOrName, 'key' => $key, 'value' => $value];

        return $this->updateMetadataResponse ?? ['updateProjectMetadata' => ['id' => 1]];
    }

    /**
     * @return array<string, mixed>
     */
    #[\Override]
    public function getProjectByName(string $projectName): array
    {
        if ($this->throwOnGetProject) {
            throw new \RuntimeException('getProjectByName failed');
        }

        if (! isset($this->projects[$projectName])) {
            // Lagoon returns a null payload for unknown projects.
            return ['projectByName' => null];
        }

        return ['projectByName' => $this->projects[$projectName]];
    }

    /**
     * @return array<string, mixed>
     */
    #[\Override]
    public function deleteProjectEnvironmentByName(string $projectName, string $environmentName): array
    {
        $this->environmentDeletes[] = ['project' => $projectName, 'environment' => $environmentName];

        return $this->deleteEnvironmentResponse ?? ['deleteEnvironment' => 'success'];
    }

    /**
     * @return array<string, mixed>
     */
    #[\Override]
    public function deleteProjectByName(string $projectName): array
    {
        $this->projectDeletes[] = $projectName;

        return $this->deleteProjectResponse ?? ['deleteProject' => 'success'];
    }

    /**
     * @return array<string, mixed>
     */
    #[\Override]
    public function addGroupToProject(string $groupName, string $projectName): array
    {
        $this->groupAdds[] = ['group' => $groupName, 'project' => $projectName];

        return $this->addGroupResponse ?? ['addGroupsToProject' => ['id' => 1]];
    }

    /** Register a canned project so getProjectByName finds it. */
    public function registerProject(string $name, int $id = 1, string $productionEnvironment = 'main', ?int $openshiftId = 7, string $gitUrl = 'git@example.com:acme/site.git'): void
    {
        $this->projects[$name] = [
            'id' => $id,
            'name' => $name,
            'productionEnvironment' => $productionEnvironment,
            'gitUrl' => $gitUrl,
            'openshift' => $openshiftId === null ? null : ['id' => $openshiftId],
        ];
    }

    /**
     * @param  list<int|array<string, mixed>>  $environments
     * @param  array<string, mixed>  $buildVariables
     * @return array<string, mixed>
     */
    #[\Override]
    public function bulkDeployEnvironments(array $environments, ?string $name = null, array $buildVariables = []): array
    {
        $this->bulkCalls[] = compact('environments', 'name', 'buildVariables');

        if ($this->throwOnDeploy) {
            throw new \RuntimeException('bulk deploy failed');
        }

        return $this->bulkResponse ?? ['bulkDeployEnvironmentLatest' => $this->bulkId];
    }

    /**
     * @return array<mixed>
     */
    #[\Override]
    public function getDeploymentsByBulkId(string $bulkId): array
    {
        if ($this->throwOnPoll) {
            throw new \RuntimeException('poll failed');
        }

        if (! empty($this->deploymentResponses)) {
            $this->lastDeployments = array_shift($this->deploymentResponses);
        }

        return $this->lastDeployments ?? [];
    }

    /**
     * Helper to build a deployment entry shaped like Lagoon's response.
     *
     * @return array<string, mixed>
     */
    public static function deployment(string $project, string $branch, string $status, string $name = 'lagoon-build-x'): array
    {
        return [
            'id' => 1,
            'name' => $name,
            'status' => $status,
            'created' => '2026-07-01T00:00:00',
            'started' => '2026-07-01T00:01:00',
            'completed' => $status === 'complete' ? '2026-07-01T00:05:00' : null,
            'environment' => [
                'name' => $branch,
                'project' => ['name' => $project],
            ],
        ];
    }
}
