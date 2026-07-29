<?php

declare(strict_types=1);

namespace App\Polydock\Apps\DependencyTrack\Traits\Create;

use App\Polydock\Core\Enums\PolydockAppInstanceStatus;
use App\Polydock\Core\PolydockAppInstanceInterface;

trait PostCreateAppInstanceTrait
{
    public function postCreateAppInstance(PolydockAppInstanceInterface $appInstance): PolydockAppInstanceInterface
    {
        $functionName = __FUNCTION__;

        // Do not validate lagoon project name and ID here either as we might only have an organisation
        return $this->runLifecyclePhase(
            $appInstance,
            $functionName,
            PolydockAppInstanceStatus::PENDING_POST_CREATE,
            PolydockAppInstanceStatus::POST_CREATE_RUNNING,
            PolydockAppInstanceStatus::POST_CREATE_COMPLETED,
            PolydockAppInstanceStatus::POST_CREATE_FAILED,
            function (PolydockAppInstanceInterface $appInstance, array $logContext) use ($functionName): ?PolydockAppInstanceInterface {
                $projectName = $appInstance->getKeyValue('lagoon-project-name');
                $environmentName = $appInstance->getKeyValue('lagoon-deploy-branch');

                $this->info("$functionName: starting for project: $projectName", $logContext);

                try {
                    // First we ensure the deploy group is attached if running a Project deployment
                    if ($projectName && $this->lagoonClient->projectExistsByName($projectName)) {
                        $addGroupToProjectResult = $this->lagoonClient->addGroupToProject(
                            $appInstance->getKeyValue('lagoon-deploy-group-name'),
                            $projectName
                        );

                        if (isset($addGroupToProjectResult['error'])) {
                            $errorMessage = \is_array($addGroupToProjectResult['error'])
                                ? ($addGroupToProjectResult['error'][0]['message'] ?? json_encode($addGroupToProjectResult['error']))
                                : $addGroupToProjectResult['error'];
                            $this->error($errorMessage);
                            throw new \Exception($errorMessage);
                        }
                    }

                    // We need to fetch the environments of the deployed Dependency Track project to find the apiserver route
                    $this->info("$functionName: checking environment routes for Dependency Track API endpoint", $logContext);
                    $environments = $this->lagoonClient->getProjectEnvironmentsByName($projectName);

                    $apiEndpoint = '';
                    foreach ($environments as $env) {
                        if ($env['name'] === $environmentName) {
                            $routes = explode(',', $env['routes'] ?? '');
                            foreach ($routes as $route) {
                                $route = trim($route);
                                // Filter the ones starting with 'apiserver.'
                                if (str_starts_with($route, 'https://apiserver.') || str_starts_with($route, 'http://apiserver.')) {
                                    $apiEndpoint = $route;
                                    break 2;
                                } elseif (str_starts_with($route, 'apiserver.')) {
                                    $apiEndpoint = "https://$route";
                                    break 2;
                                }
                            }
                        }
                    }

                    if (! $apiEndpoint) {
                        $this->error('Could not determine Dependency Track API endpoint (no route starting with apiserver.)');
                        throw new \Exception('Missing API route for Dependency Track');
                    }

                    $this->info("$functionName: determined API Endpoint: $apiEndpoint", $logContext);

                    // TODO: Execute CLI script inside the environment to get the API Key (TODO)
                    $apiKey = $appInstance->getKeyValue('app-admin-api-key');
                    if (empty($apiKey)) {
                        throw new \Exception('Missing Dependency-Track API key on app instance. Claim must run before post-create can publish organization variables.');
                    }

                    $this->info("$functionName: using API Key captured during claim", $logContext);

                    // Now we inject into target Organisation or Project
                    $lagoonOrgName = $appInstance->getKeyValue('lagoon_organisation');

                    if (! empty($lagoonOrgName)) {
                        $this->info("$functionName: Injecting LAGOON_FEATURE_FLAG_INSIGHTS_DEPENDENCY_TRACK_API variables into Lagoon Organisation", $logContext);
                        $this->lagoonClient->addOrUpdateGlobalVariableForOrganization(
                            $lagoonOrgName,
                            'LAGOON_FEATURE_FLAG_INSIGHTS_DEPENDENCY_TRACK_API_ENDPOINT',
                            $apiEndpoint
                        );
                        $this->lagoonClient->addOrUpdateGlobalVariableForOrganization(
                            $lagoonOrgName,
                            'LAGOON_FEATURE_FLAG_INSIGHTS_DEPENDENCY_TRACK_API_KEY',
                            $apiKey
                        );
                    }

                } catch (\Exception $e) {
                    $this->error('Post Create Failed: '.$e->getMessage(), [
                        'exception_class' => get_class($e),
                        'exception_trace' => $e->getTraceAsString(),
                    ]);

                    $appInstance->setStatus(PolydockAppInstanceStatus::POST_CREATE_FAILED, 'An exception occurred: '.$e->getMessage())->save();

                    return $appInstance;
                }

                return null;
            },
            'Post-create completed',
            validateLagoonProjectName: false,
            validateLagoonProjectId: false,
        );
    }
}
