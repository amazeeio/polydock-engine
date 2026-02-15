<?php

namespace App\Console\Commands;

use App\Enums\PolydockStoreAppStatusEnum;
use App\Models\PolydockStore;
use App\Models\PolydockStoreApp;
use App\Services\PolydockAppClassDiscovery;
use Illuminate\Console\Command;

class CreateStoreApp extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'polydock:create-store-app
                          {--store-id= : Store ID to create app in}
                          {--name= : App name}
                          {--app-class= : Polydock app class}
                          {--description= : App description}
                          {--author= : App author}
                          {--website= : Author website}
                          {--support-email= : Support email}
                          {--git= : Lagoon deploy git repository}
                          {--branch= : Lagoon deploy branch}
                          {--status= : App status}
                          {--trials= : Available for trials (true/false)}
                          {--target-instances= : Target unallocated app instances}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new App in a Polydock Store';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Creating new App in Polydock Store...');

        // Get all stores for selection
        $stores = PolydockStore::all();

        if ($stores->isEmpty()) {
            $this->error('No stores found. Please create a store first.');

            return 1;
        }

        // Select store
        $storeId = $this->option('store-id');
        if (! $storeId) {
            $storeOptions = $stores
                ->mapWithKeys(fn ($store) => [$store->id => "{$store->name} (ID: {$store->id})"])
                ->toArray();

            $selectedValue = $this->choice('Select a store to create app in:', $storeOptions);
            $storeId = collect($storeOptions)->search($selectedValue);
        }

        // Validate store exists
        $store = PolydockStore::find($storeId);
        if (! $store) {
            $this->error("Store with ID {$storeId} not found.");

            return 1;
        }

        // Gather app information
        $name = $this->option('name') ?? $this->ask('App name');
        $discovery = app(PolydockAppClassDiscovery::class);
        $availableClasses = $discovery->getAvailableAppClasses();
        $appClass = $this->option('app-class');
        if ($appClass) {
            if (! $discovery->isValidAppClass($appClass)) {
                $this->error("Invalid app class: {$appClass}. Must be a concrete PolydockAppInterface implementation.");

                return 1;
            }
        } else {
            if (empty($availableClasses)) {
                $this->error('No Polydock app classes found. Ensure packages are installed correctly.');

                return 1;
            }
            $appClass = $this->choice('Select Polydock app class', array_keys($availableClasses));
        }
        $description = $this->option('description') ?? $this->ask('App description');
        $author = $this->option('author') ?? $this->ask('Author');
        $website = $this->option('website') ?? $this->ask('Author website');
        $supportEmail = $this->option('support-email') ?? $this->ask('Support email');
        $git = $this->option('git') ?? $this->ask('Lagoon deploy git repository');
        $branch = $this->option('branch') ?? $this->ask('Lagoon deploy branch');

        $statusInput = $this->option('status') ?? $this->choice('App status', [
            'available' => 'Available',
            'unavailable' => 'Unavailable',
            'deprecated' => 'Deprecated',
        ]);
        $status = match ($statusInput) {
            'available', 'Available' => PolydockStoreAppStatusEnum::AVAILABLE,
            'unavailable', 'Unavailable' => PolydockStoreAppStatusEnum::UNAVAILABLE,
            'deprecated', 'Deprecated' => PolydockStoreAppStatusEnum::DEPRECATED,
            default => PolydockStoreAppStatusEnum::AVAILABLE,
        };

        $trialsInput = $this->option('trials') ?? $this->choice('Available for trials?', ['true', 'false']);
        $trials = filter_var($trialsInput, FILTER_VALIDATE_BOOLEAN);

        $targetInstances = $this->option('target-instances') ?? $this->ask('Target unallocated app instances');
        if ($targetInstances == '') {
            $targetInstances = 0;
        }

        // Check if all required values are set
        if (
            empty($name)
            || empty($appClass)
            || empty($description)
            || empty($author)
            || empty($website)
            || empty($supportEmail)
            || empty($git)
            || empty($branch)
            || $targetInstances != 0 && empty($targetInstances)
        ) {
            $this->error('All fields are required. Exiting...');

            return 1;
        }

        // Create the store app
        $storeApp = PolydockStoreApp::create([
            'polydock_store_id' => $store->id,
            'name' => $name,
            'polydock_app_class' => $appClass,
            'description' => $description,
            'author' => $author,
            'website' => $website,
            'support_email' => $supportEmail,
            'lagoon_deploy_git' => $git,
            'lagoon_deploy_branch' => $branch,
            'status' => $status,
            'available_for_trials' => $trials,
            'target_unallocated_app_instances' => intval($targetInstances),
        ]);

        $this->info(
            "✅ App '{$storeApp->name}' created successfully in store '{$store->name}' with ID: {$storeApp->id}",
        );
        $this->line("   App Class: {$storeApp->polydock_app_class}");
        $this->line("   Git Repository: {$storeApp->lagoon_deploy_git}");
        $this->line('   Available for Trials: '.($storeApp->available_for_trials ? 'Yes' : 'No'));

        return 0;
    }
}
