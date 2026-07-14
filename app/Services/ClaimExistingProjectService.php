<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PolydockAppInstance;
use App\Models\PolydockStoreApp;
use App\Models\UserGroup;
use App\Polydock\Core\Enums\PolydockAppInstanceStatus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Adopts an existing Lagoon project as a Polydock app instance.
 *
 * The normal lifecycle builds a project from scratch (PRE_CREATE → CREATE →
 * … → POST_DEPLOY). A claimed project already exists on Lagoon, so we skip all
 * of that: verify the project, grant Polydock's deploy group access to it, and
 * land a new instance directly at RUNNING_HEALTHY_CLAIMED. From there the
 * existing scheduled-redeploy machinery (PolydockDeploymentService) auto-updates
 * it exactly like a natively-created instance.
 *
 * Claimed instances carry data['adopted'] = true. That flag makes REMOVE detach
 * (leave the Lagoon environment intact) and purge skip project deletion —
 * Polydock never destroys a project it did not create.
 */
class ClaimExistingProjectService
{
    public function __construct(private LagoonClientService $lagoonClientService) {}

    public function claim(PolydockStoreApp $storeApp, UserGroup $userGroup, string $projectName): PolydockAppInstance
    {
        $projectName = trim($projectName);
        if ($projectName === '') {
            throw new \InvalidArgumentException('A Lagoon project name is required.');
        }

        // Serialize claims for the same project. There is no DB-level unique
        // constraint on the `data->lagoon-project-name` JSON path, so without
        // this lock two concurrent admin requests could both pass the uniqueness
        // check, both grant group access, and both insert — two instances owning
        // one Lagoon project. The lock closes that window cheaply.
        $lock = Cache::lock('claim-lagoon-project:'.$projectName, 30);
        if (! $lock->get()) {
            throw new \RuntimeException("A claim for '{$projectName}' is already in progress.");
        }

        try {
            // Don't track the same project twice. Soft-deleted (detached/purged)
            // instances are excluded by the default scope, so re-claiming works.
            // The `->` JSON operator compiles to correctly-quoted, driver-specific
            // SQL on MariaDB/MySQL and sqlite alike.
            $existing = PolydockAppInstance::query()->where('data->lagoon-project-name', $projectName)->first();
            if ($existing) {
                throw new \RuntimeException("Lagoon project '{$projectName}' is already tracked by instance #{$existing->id}.");
            }

            $client = $this->lagoonClientService->getAuthenticatedClient();

            // Preflight: the project must exist and be visible to Polydock's token.
            $response = $client->getProjectByName($projectName);
            $project = $response['projectByName'] ?? null;
            if (isset($response['error']) || ! isset($project['id'])) {
                throw new \RuntimeException("Lagoon project '{$projectName}' was not found or is not visible to Polydock.");
            }

            // Grant Polydock's deploy group access to the existing project — the
            // same access native projects get at PostCreate, and what lets the
            // global token trigger redeploys. Fail loudly if Lagoon rejects it,
            // rather than create an instance that can never deploy.
            //
            // Ordering is deliberate: the grant happens before the DB write. If
            // the (local-only, no-I/O) transaction below still somehow fails, the
            // leftover is our own group attached to a project with no tracking
            // instance — benign and self-heals on re-claim. The reverse ordering
            // would instead leave a live instance that can't deploy, which is the
            // worse silent-failure mode. Lagoon has no removeGroupFromProject
            // mutation to revert with anyway.
            $groupName = $storeApp->lagoon_deploy_group_name;
            if (! empty($groupName)) {
                $groupResponse = $client->addGroupToProject($groupName, $projectName);
                if (isset($groupResponse['error'])) {
                    throw new \RuntimeException(
                        "Failed to add group '{$groupName}' to project '{$projectName}': ".json_encode($groupResponse['error'])
                    );
                }
            }

            $productionEnvironment = $project['productionEnvironment'] ?? $storeApp->lagoon_deploy_branch;
            $regionId = $project['openshift']['id'] ?? $storeApp->lagoon_deploy_region_id_ext;

            return DB::transaction(function () use ($storeApp, $userGroup, $projectName, $project, $groupName, $productionEnvironment, $regionId) {
                // The creating hook seeds `data` from the store app (deploy keys,
                // group, region, health webhook, generated creds). We then overwrite
                // the identity/lifecycle keys to point at the real project.
                $instance = new PolydockAppInstance;
                $instance->polydock_store_app_id = $storeApp->id;
                $instance->user_group_id = $userGroup->id;
                $instance->name = $projectName;
                $instance->is_trial = false; // adopted projects are never trials; required for scheduled redeploys
                $instance->save();

                $data = $instance->data ?? [];
                $data['lagoon-project-name'] = $projectName;
                $data['lagoon-project-id'] = $project['id'];
                $data['lagoon-deploy-branch'] = $productionEnvironment;
                $data['lagoon-production-environment'] = $productionEnvironment;
                $data['lagoon-deploy-region-id'] = $regionId;
                $data['adopted'] = true;
                if (! empty($groupName)) {
                    $data['adopted-added-group'] = $groupName;
                }
                if (! empty($project['gitUrl'])) {
                    $data['lagoon-deploy-git'] = $project['gitUrl'];
                }
                $instance->data = $data;

                // The creating hook's name-uniqueness loop may have appended a
                // random suffix if another instance already held this name. For an
                // adopted project the display name must equal the real Lagoon
                // project name, so restore it. ponytail: `name` has no DB unique
                // constraint (the hook dedupes in-app) and the per-project lock +
                // existence check above already rule out a second adopted claim,
                // so a genuine collision would need a pool/native instance named
                // exactly this external project — negligible.
                $instance->name = $projectName;

                // Land directly at the finish line. The ProcessNewJob queued by the
                // create event will see the advanced status and skip
                // (shouldSkipBecauseStatusAdvanced), so the create/deploy pipeline
                // never runs against the existing project.
                $instance->setStatus(
                    PolydockAppInstanceStatus::RUNNING_HEALTHY_CLAIMED,
                    'Adopted existing Lagoon project'
                )->save();

                return $instance;
            });
        } finally {
            $lock->release();
        }
    }
}
