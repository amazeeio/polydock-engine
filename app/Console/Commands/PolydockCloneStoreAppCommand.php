<?php

namespace App\Console\Commands;

use App\Models\PolydockStore;
use App\Models\PolydockStoreApp;
use App\Enums\PolydockStoreAppStatusEnum;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PolydockCloneStoreAppCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'polydock:clone-store-app {app_id : The ID of the PolydockStoreApp to clone}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clone a PolydockStoreApp into a different store';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $appId = $this->argument('app_id');

        try {
            // Find the source app
            $sourceApp = PolydockStoreApp::findOrFail($appId);
            $this->info("Found source app: {$sourceApp->name}");

            // Get available stores
            $stores = PolydockStore::all();
            if ($stores->isEmpty()) {
                $this->error('No stores found to clone into.');
                return 1;
            }

            // Create store selection array
            $storeChoices = $stores->mapWithKeys(function ($store) {
                return [$store->id => "{$store->name} (ID: {$store->id})"];
            })->toArray();

            // Ask user which store to clone into
            $targetStoreId = array_search($this->choice(
                'Select target store to clone into:',
                $storeChoices,
                null,
                null,
                false
            ), $storeChoices);

            $this->info("Selected target store ID: {$targetStoreId}");

            $targetStore = PolydockStore::find($targetStoreId);
            $this->info("Selected target store: {$targetStore->name}");

            // Ask for GitHub repository
            $defaultRepo = $sourceApp->lagoon_deploy_git;
            $gitRepo = $this->ask('Enter GitHub repository to deploy:', $defaultRepo);

            // Clone the app
            $newApp = $sourceApp->replicate();
            $newApp->polydock_store_id = $targetStore->id;
            $newApp->status = PolydockStoreAppStatusEnum::UNAVAILABLE;
            $newApp->name = $this->ask('Enter name for the cloned app:', $sourceApp->name . ' (Clone)');
            $newApp->lagoon_deploy_git = $gitRepo;
            $newApp->target_unallocated_app_instances = 0;
            $newApp->save();

            // Clone variables
            foreach ($sourceApp->variables as $variable) {
                $newVariable = $variable->replicate();
                $newVariable->variabled_id = $newApp->id;
                $newVariable->save();
            }

            $this->info('App cloned successfully!');
            $this->table(
                ['Field', 'Value'],
                [
                    ['ID', $newApp->id],
                    ['UUID', $newApp->uuid],
                    ['Name', $newApp->name],
                    ['Store', $targetStore->name],
                    ['Status', $newApp->status->value],
                ]
            );

            Log::info('Store app cloned successfully', [
                'source_app_id' => $sourceApp->id,
                'new_app_id' => $newApp->id,
                'target_store_id' => $targetStore->id
            ]);

        } catch (\Exception $e) {
            $this->error("Failed to clone app: {$e->getMessage()}");
            Log::error('Failed to clone store app', [
                'error' => $e->getMessage(),
                'source_app_id' => $appId
            ]);
            return 1;
        }

        return 0;
    }
} 