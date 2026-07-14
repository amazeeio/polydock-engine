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

    public ?array $addGroupResponse = null;

    public function __construct()
    {
        // Intentionally bypass the real Client constructor (SSH/config setup).
    }

    #[\Override]
    public function getProjectByName(string $projectName): array
    {
        if (! isset($this->projects[$projectName])) {
            // Lagoon returns a null payload for unknown projects.
            return ['projectByName' => null];
        }

        return ['projectByName' => $this->projects[$projectName]];
    }

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

    #[\Override]
    public function bulkDeployEnvironments(array $environments, ?string $name = null, array $buildVariables = []): array
    {
        $this->bulkCalls[] = compact('environments', 'name', 'buildVariables');

        if ($this->throwOnDeploy) {
            throw new \RuntimeException('bulk deploy failed');
        }

        return $this->bulkResponse ?? ['bulkDeployEnvironmentLatest' => $this->bulkId];
    }

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
