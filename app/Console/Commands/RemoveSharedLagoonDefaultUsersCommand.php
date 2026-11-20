<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\PolydockAppInstance;
use App\Services\LagoonClientService;
use Exception;
use Illuminate\Console\Command;

/**
 * Projects created with the old shared store deploy key all contain the same
 * Lagoon default-user (default-user@<first project that used the key>), which
 * lets any one project ssh into all the others, including that first project.
 * Polydock reaches projects with its own identity, never a default-user, so
 * every default-user@ member is removed, the project's own one included.
 */
class RemoveSharedLagoonDefaultUsersCommand extends BaseCommand
{
    protected $signature = 'polydock:remove-shared-lagoon-default-users
                            {--uuid= : Only process a specific app instance UUID}
                            {--dry-run : Only list what would be removed}
                            {--force : Skip confirmation prompt}';

    protected $description = 'Removes default-users (incl. the one shared via the old store deploy key) from every instance\'s Lagoon project group that still exists';

    public function handle(): int
    {
        // Every status: removed/purge-failed instances can still have live Lagoon projects.
        // Lagoon itself decides below which projects are gone.
        $query = PolydockAppInstance::query();

        $uuid = $this->option('uuid');
        if (is_string($uuid) && $uuid !== '') {
            $query->where('uuid', $uuid);
        }

        $instances = $query->get();
        $dryRun = $this->option('dry-run') === true;

        $this->info(sprintf('Found %d app instance(s).', $instances->count()));

        if ($instances->isEmpty()) {
            return Command::SUCCESS;
        }

        if (! $dryRun && ! $this->option('force') && ! $this->confirm('Remove shared default-users from these projects?')) {
            $this->info('Operation cancelled.');

            return Command::SUCCESS;
        }

        try {
            $client = app(LagoonClientService::class)->getAuthenticatedClient();
        } catch (Exception $e) {
            $this->error('Failed to authenticate Lagoon client: '.$e->getMessage());

            return Command::FAILURE;
        }

        $removed = 0;
        $failed = 0;

        foreach ($instances as $instance) {
            $projectName = $instance->getKeyValue('lagoon-project-name');
            if (! is_string($projectName) || $projectName === '') {
                continue;
            }

            // Adopted projects pre-date Polydock; their default-users belong to the owner, not the shared store key.
            if (filter_var($instance->getKeyValue('adopted'), FILTER_VALIDATE_BOOLEAN)) {
                $this->line(sprintf('%s: adopted project, skipped', $projectName));

                continue;
            }

            try {
                $project = $client->getProjectByName($projectName);
            } catch (Exception $e) {
                $project = ['error' => $e->getMessage()];
            }

            if (! array_key_exists('projectByName', $project)) {
                $this->error(sprintf('%s: failed to look up project: %s', $projectName, json_encode($project['error'] ?? $project)));
                $failed++;

                continue;
            }

            if ($project['projectByName'] === null) {
                continue; // project no longer exists in Lagoon
            }

            $groupName = "project-{$projectName}";

            try {
                $members = $client->getGroupUsers($groupName);
            } catch (Exception $e) {
                $members = ['error' => $e->getMessage()];
            }

            if (isset($members['error'])) {
                $this->error(sprintf('%s: failed to list group members: %s', $projectName, json_encode($members['error'])));
                $failed++;

                continue;
            }

            $sharedUsers = array_unique(array_filter(
                $members,
                fn (mixed $email): bool => is_string($email) && str_starts_with($email, 'default-user@'),
            ));

            foreach ($sharedUsers as $email) {
                if ($dryRun) {
                    $this->line(sprintf('%s: would remove %s', $projectName, $email));

                    continue;
                }

                try {
                    $result = $client->removeUserFromGroup($groupName, $email);
                } catch (Exception $e) {
                    $result = ['error' => $e->getMessage()];
                }

                $removedGroup = $result['removeUserFromGroup'] ?? null;
                if (! is_array($removedGroup) || ! isset($removedGroup['id'])) {
                    $this->error(sprintf('%s: failed to remove %s: %s', $projectName, $email, json_encode($result['error'] ?? $result)));
                    $failed++;
                } else {
                    $this->line(sprintf('%s: removed %s', $projectName, $email));
                    $removed++;
                }
            }
        }

        $this->info(sprintf('Done: %d removed, %d failed.', $removed, $failed));

        return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
