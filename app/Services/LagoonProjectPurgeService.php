<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PolydockAppInstance;
use App\Polydock\Clients\Lagoon\Client;
use App\Polydock\Core\PolydockAppLoggerInterface;
use App\PolydockEngine\PolydockLogger;
use App\PolydockServiceProviders\PolydockServiceProviderFTLagoon;
use Throwable;

/**
 * Encapsulates the logic for fully deleting a Lagoon project that backs a
 * PolydockAppInstance. Used by both the automated purge job and the manual
 * polydock:remove-empty-projects artisan command.
 */
class LagoonProjectPurgeService
{
    /**
     * Last failure reason captured by attemptPurge(). Useful for callers that
     * want to record the message without re-querying Lagoon.
     */
    public ?string $lastFailureReason = null;

    /**
     * Number of environments seen on the Lagoon project during the most recent
     * call to attemptPurge(). Null if the call did not need to inspect envs.
     */
    public ?int $lastEnvironmentCount = null;

    public function __construct(
        protected PolydockAppLoggerInterface $logger,
        protected ?Client $client = null,
    ) {}

    /**
     * Build a service with a freshly authenticated Lagoon client.
     */
    public static function makeWithDefaults(?PolydockAppLoggerInterface $logger = null): self
    {
        $logger ??= new PolydockLogger;

        if (app()->bound(Client::class)) {
            return new self($logger, app(Client::class));
        }

        $serviceProvider = new PolydockServiceProviderFTLagoon(
            config('polydock.service_providers_singletons.PolydockServiceProviderFTLagoon'),
            $logger,
        );

        return new self($logger, $serviceProvider->getLagoonClient());
    }

    /**
     * Resolve the Lagoon client lazily so consumers that already have a
     * configured client (e.g. a long-running command) don't re-auth per call.
     */
    protected function client(): Client
    {
        if ($this->client === null) {
            if (app()->bound(Client::class)) {
                $this->client = app(Client::class);
            } else {
                $serviceProvider = new PolydockServiceProviderFTLagoon(
                    config('polydock.service_providers_singletons.PolydockServiceProviderFTLagoon'),
                    $this->logger,
                );
                $this->client = $serviceProvider->getLagoonClient();
            }
        }

        return $this->client;
    }

    /**
     * Fetch the raw Lagoon project payload by name.
     */
    public function getProjectByName(string $projectName): mixed
    {
        return $this->client()->getProjectByName($projectName);
    }

    /**
     * Resolve the Lagoon project name for an instance the same way the rest of
     * the engine does (matches RemoveEmptyProjectsCommand).
     */
    public function resolveProjectName(PolydockAppInstance $instance): ?string
    {
        $name = $instance->data['project_name'] ?? $instance->name ?? null;

        return is_string($name) && $name !== '' ? $name : null;
    }

    /**
     * Check if a Lagoon environment is considered active (not deleted).
     */
    public static function isActiveEnvironment(array $env): bool
    {
        $deleted = $env['deleted'] ?? null;

        return $deleted === null || $deleted === '' || $deleted === '0000-00-00 00:00:00';
    }

    /**
     * Make one attempt to fully delete the Lagoon project.
     *
     * Behavior:
     *   - Returns AlreadyGone if Lagoon does not know about the project.
     *   - Returns StillHasEnvironments if any environments are still listed.
     *   - Calls deleteProjectByName when the project has zero environments.
     */
    public function attemptPurge(PolydockAppInstance $instance): PurgeResult
    {
        $this->lastFailureReason = null;
        $this->lastEnvironmentCount = null;

        $projectName = $this->resolveProjectName($instance);

        if ($projectName === null) {
            $this->lastFailureReason = 'No Lagoon project name on instance';
            $this->logger->warning('Cannot purge instance: no project name', [
                'app_instance_id' => $instance->id,
            ]);

            return PurgeResult::MissingProjectName;
        }

        try {
            $projectData = $this->getProjectByName($projectName);

            if (is_array($projectData) && array_key_exists('projectByName', $projectData)) {
                $projectData = $projectData['projectByName'];
            }
        } catch (Throwable $e) {
            $this->lastFailureReason = 'getProjectByName threw: '.$e->getMessage();
            $this->logger->error('Failed to fetch Lagoon project for purge', [
                'app_instance_id' => $instance->id,
                'project_name' => $projectName,
                'error' => $e->getMessage(),
            ]);

            return PurgeResult::Failed;
        }

        // Treat null/empty project payload as already-gone.
        if (empty($projectData) || (isset($projectData['error']) && $projectData['error'])) {
            // If the API reported an explicit error (other than "not found"),
            // record it. Lagoon's getProjectByName returns null/empty for
            // missing projects rather than an error key, so this branch is for
            // genuine API failures.
            if (isset($projectData['error'])) {
                $this->lastFailureReason = 'Lagoon API error: '.json_encode($projectData['error']);

                return PurgeResult::Failed;
            }

            $this->logger->info('Lagoon project already gone', [
                'app_instance_id' => $instance->id,
                'project_name' => $projectName,
            ]);

            return PurgeResult::AlreadyGone;
        }

        if (! is_array($projectData)) {
            $this->lastFailureReason = 'Lagoon project payload had unexpected type';
            $this->logger->error('Unexpected Lagoon project payload type while purging', [
                'app_instance_id' => $instance->id,
                'project_name' => $projectName,
                'payload_type' => gettype($projectData),
            ]);

            return PurgeResult::Failed;
        }

        if (! array_key_exists('environments', $projectData) || ! is_array($projectData['environments'])) {
            $this->lastFailureReason = 'Lagoon project payload missing environments list';
            $this->logger->error('Unexpected Lagoon project payload shape while purging', [
                'app_instance_id' => $instance->id,
                'project_name' => $projectName,
                'response_keys' => is_array($projectData) ? array_keys($projectData) : null,
            ]);

            return PurgeResult::Failed;
        }

        $environments = $projectData['environments'];

        // Filter out environments that are already deleted in Lagoon.
        // Lagoon GraphQL returns both active and deleted environments in the list.
        // Deleted environments have a non-null, non-empty, and non-zero 'deleted' timestamp.
        $activeEnvironments = array_filter($environments, [self::class, 'isActiveEnvironment']);

        $this->lastEnvironmentCount = count($activeEnvironments);

        if ($this->lastEnvironmentCount > 0) {
            // Actively delete each lingering environment.
            $this->logger->info('Deleting lingering environments before project purge', [
                'app_instance_id' => $instance->id,
                'project_name' => $projectName,
                'environment_count' => $this->lastEnvironmentCount,
            ]);

            foreach ($activeEnvironments as $env) {
                $envName = $env['name'] ?? null;
                if ($envName === null) {
                    continue;
                }

                try {
                    $deleteResponse = $this->client()->deleteProjectEnvironmentByName($projectName, $envName);
                    if (isset($deleteResponse['error'])) {
                        $this->logger->warning('Failed to delete lingering environment', [
                            'app_instance_id' => $instance->id,
                            'project_name' => $projectName,
                            'environment' => $envName,
                            'error' => json_encode($deleteResponse['error']),
                        ]);
                    } else {
                        $this->logger->info('Deleted lingering environment', [
                            'app_instance_id' => $instance->id,
                            'project_name' => $projectName,
                            'environment' => $envName,
                        ]);
                    }
                } catch (Throwable $e) {
                    $this->logger->warning('Failed to delete lingering environment', [
                        'app_instance_id' => $instance->id,
                        'project_name' => $projectName,
                        'environment' => $envName,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // After issuing deletes, the environments won't be gone instantly.
            // Return StillHasEnvironments so the caller retries on the next tick.
            $this->lastFailureReason = sprintf(
                'Issued delete for %d environment(s); waiting for removal',
                $this->lastEnvironmentCount,
            );

            return PurgeResult::StillHasEnvironments;
        }

        try {
            $deleteResponse = $this->client()->deleteProjectByName($projectName);
        } catch (Throwable $e) {
            $this->lastFailureReason = 'deleteProjectByName threw: '.$e->getMessage();
            $this->logger->error('Failed to call deleteProjectByName', [
                'app_instance_id' => $instance->id,
                'project_name' => $projectName,
                'error' => $e->getMessage(),
            ]);

            return PurgeResult::Failed;
        }

        if (isset($deleteResponse['error'])) {
            $this->lastFailureReason = 'Lagoon deleteProject error: '.json_encode($deleteResponse['error']);
            $this->logger->error('Lagoon refused to delete project', [
                'app_instance_id' => $instance->id,
                'project_name' => $projectName,
                'response' => $deleteResponse,
            ]);

            return PurgeResult::Failed;
        }

        $this->logger->info('Lagoon project successfully deleted', [
            'app_instance_id' => $instance->id,
            'project_name' => $projectName,
        ]);

        return PurgeResult::Purged;
    }
}
