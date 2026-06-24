<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\PolydockAppInstance;
use App\Polydock\Core\Enums\PolydockAppInstanceStatus;
use App\Services\LagoonClientService;
use Illuminate\Console\Command;

class SyncLagoonMetadata extends BaseCommand
{
    protected $signature = 'polydock:sync-metadata
                            {--instance-id= : Sync only a specific app instance ID}
                            {--uuid= : Sync only a specific app instance UUID}
                            {--app-id= : Sync only instances of a specific store app ID}
                            {--email= : Sync only instances owned by a specific email address}
                            {--limit= : Limit the number of instances to sync}
                            {--force : Skip confirmation prompt}';

    protected $description = 'Syncs active Polydock App Instances metadata (email, product-type, firstname, lastname, polydock-env) to Lagoon';

    public function handle(): int
    {
        $instanceId = $this->option('instance-id');
        $uuid = $this->option('uuid');
        $appId = $this->option('app-id');
        $email = $this->option('email');
        $limit = $this->option('limit');

        $query = PolydockAppInstance::with(['storeApp.productType'])->whereNotIn('status', [
            PolydockAppInstanceStatus::REMOVED,
            PolydockAppInstanceStatus::PURGE_RUNNING,
            PolydockAppInstanceStatus::PURGE_FAILED,
        ]);

        if ($instanceId) {
            $query->where('id', $instanceId);
        }

        if ($uuid) {
            $query->where('uuid', $uuid);
        }

        if ($appId) {
            $query->where('polydock_store_app_id', $appId);
        }

        if ($email) {
            $driver = $query->getConnection()->getDriverName();
            if ($driver === 'sqlite') {
                $query->whereRaw('LOWER(json_extract(data, \'$."user-email"\')) = ?', [strtolower($email)]);
            } else {
                $query->whereRaw('LOWER(json_unquote(json_extract(data, \'$."user-email"\'))) = ?', [strtolower($email)]);
            }
        }

        if ($limit) {
            $query->limit((int) $limit);
        }

        $instances = $query->get();

        if ($instances->isEmpty()) {
            $this->info('No matching active app instances found.');

            return Command::SUCCESS;
        }

        $this->info(sprintf('Found %d active app instance(s) to sync.', $instances->count()));

        if (! $this->option('force') && ! $this->confirm('Do you want to proceed with metadata synchronization?')) {
            $this->info('Operation cancelled.');

            return Command::SUCCESS;
        }

        $client = null;
        try {
            $client = app(LagoonClientService::class)->getAuthenticatedClient([
                'timeout' => 60.0,
                'connect_timeout' => 10.0,
            ]);
        } catch (\Exception $e) {
            $this->error('Failed to authenticate Lagoon client: '.$e->getMessage());

            return Command::FAILURE;
        }

        $successCount = 0;
        $failedCount = 0;

        foreach ($instances as $instance) {
            $projectName = $instance->getKeyValue('lagoon-project-name');
            if (empty($projectName)) {
                $this->warn(sprintf('Skipping instance %s (ID: %d) because no lagoon-project-name was found.', $instance->name, $instance->id));
                $failedCount++;

                continue;
            }

            $this->info(sprintf('Syncing metadata for %s (Project: %s)...', $instance->name, $projectName));

            // Resolve values
            $emailValue = $instance->getKeyValue('user-email');
            $firstName = $instance->getKeyValue('user-first-name');
            $lastName = $instance->getKeyValue('user-last-name');

            $productType = $instance->getKeyValue('product-type') ?: 'generic';
            if ($productType === 'generic' && $instance->storeApp) {
                if ($instance->storeApp->productType) {
                    $productType = $instance->storeApp->productType->slug;
                }
            }

            $lagoonEnv = config('polydock.lagoon_environment_type', 'development');
            $polydockEnv = ($lagoonEnv === 'production' || config('app.env') === 'production') ? 'prod' : 'dev';

            $metadataPayload = array_filter([
                'email' => $emailValue,
                'product-type' => $productType,
                'firstname' => $firstName,
                'lastname' => $lastName,
                'polydock-env' => $polydockEnv,
            ]);

            // Query Lagoon for current project details and metadata to check if changes exist
            $projectId = null;
            $existingMetadata = [];
            try {
                $projectResponse = $client->getProjectByName($projectName);
                $projectData = $projectResponse['projectByName'] ?? null;
                if ($projectData === null) {
                    $this->error('  - Project not found on Lagoon.');
                    $failedCount++;

                    continue;
                }
                if (isset($projectData['id'])) {
                    $projectId = (int) $projectData['id'];
                }
                if (! empty($projectData['metadata'])) {
                    if (is_array($projectData['metadata'])) {
                        $existingMetadata = $projectData['metadata'];
                    } elseif (is_string($projectData['metadata'])) {
                        $existingMetadata = json_decode($projectData['metadata'], true) ?: [];
                    }
                }
            } catch (\Exception $e) {
                $this->warn(sprintf('  - Failed to fetch existing project metadata: %s. Proceeding with safety writes.', $e->getMessage()));
            }

            $hasError = false;
            $keysWritten = 0;
            foreach ($metadataPayload as $key => $value) {
                $existingValue = $existingMetadata[$key] ?? null;
                if ($existingValue !== null && (string) $existingValue === (string) $value) {
                    continue; // Skip if already matches!
                }

                try {
                    $result = $client->updateProjectMetadata($projectId ?? $projectName, $key, (string) $value);

                    if (isset($result['error'])) {
                        $errorMsg = is_array($result['error']) ? json_encode($result['error']) : $result['error'];
                        $this->error(sprintf('  - Failed to write metadata "%s": %s', $key, $errorMsg));
                        $hasError = true;
                    } else {
                        $this->line(sprintf('  - Set metadata: %s => %s', $key, $value));
                        $keysWritten++;
                    }
                } catch (\Exception $e) {
                    $this->error(sprintf('  - Exception writing metadata "%s": %s', $key, $e->getMessage()));
                    $hasError = true;
                }
            }

            if ($hasError) {
                $failedCount++;
            } else {
                $successCount++;
                if ($keysWritten === 0) {
                    $this->line('  - All metadata already in sync.');
                }
            }
        }

        $this->newLine();
        $this->info(sprintf('Sync completed: %d succeeded, %d failed/skipped.', $successCount, $failedCount));

        return $failedCount > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
