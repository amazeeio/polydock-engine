<?php

namespace App\Console\Commands;

use App\Enums\PolydockStoreStatusEnum;
use App\Models\PolydockStore;
use Illuminate\Console\Command;

class CreateStore extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'polydock:create-store
                          {--name= : Store name}
                          {--status= : Store status (public/private)}
                          {--listed= : Listed in marketplace (true/false)}
                          {--region-id= : Lagoon deploy region ID}
                          {--prefix= : Lagoon deploy project prefix}
                          {--org-id= : Lagoon deploy organization ID}
                          {--ai-region-id= : Amazee AI backend region ID}
                          {--group-name= : Lagoon deploy group name}
                          {--deploy-key= : Custom deploy private key (optional)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new Polydock Store with interactive prompts';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Creating a new Polydock Store...');

        // Gather store information
        $name = $this->option('name') ?? $this->ask('Store name');

        $status = $this->option('status') ?? $this->choice(
            'Store status',
            ['public', 'private'],
        );

        $listedInput = $this->option('listed') ?? $this->choice(
            'Listed in marketplace?',
            ['true', 'false'],
        );
        $listed = filter_var($listedInput, FILTER_VALIDATE_BOOLEAN);

        $regionId = $this->option('region-id') ?? $this->ask('Lagoon deploy region ID');

        $prefix = $this->option('prefix') ?? $this->ask('Lagoon deploy project prefix');

        $orgId = $this->option('org-id') ?? $this->ask('Lagoon deploy organization ID');

        $aiRegionId = $this->option('ai-region-id') ?? $this->ask('Amazee AI backend region ID');

        $groupName = $this->option('group-name') ?? $this->ask('Lagoon deploy group name');

        // Check if all required values are set
        if (
            empty($name)
            || empty($status)
            || empty($regionId)
            || empty($prefix)
            || empty($orgId)
            || empty($aiRegionId)
            || empty($groupName)
        ) {
            $this->error('All fields are required. Exiting...');

            return 1;
        }

        // Get deploy key - allow override
        $customDeployKey = $this->option('deploy-key');

        if (
            ! $customDeployKey
            && $this->confirm('Do you want to use a custom deploy private key? (Press no to use default from config)')
        ) {
            $this->info('Please paste your private key (multi-line input supported):');
            $customDeployKey = $this->secret('Deploy private key');
        }

        if ($customDeployKey) {
            $deployKey = $customDeployKey;
        } else {
            $deployKey = file_get_contents(config('polydock.lagoon_deploy_private_key_file'));
        }

        if (empty($deployKey)) {
            $this->error('No deploy key available - either provide one or ensure config file exists');

            return 1;
        }

        // Create the store
        $store = PolydockStore::create([
            'name' => $name,
            'status' => $status === 'public' ? PolydockStoreStatusEnum::PUBLIC : PolydockStoreStatusEnum::PRIVATE,
            'listed_in_marketplace' => $listed,
            'lagoon_deploy_region_id_ext' => $regionId,
            'lagoon_deploy_project_prefix' => $prefix,
            'lagoon_deploy_organization_id_ext' => $orgId,
            'amazee_ai_backend_region_id_ext' => $aiRegionId,
            'lagoon_deploy_group_name' => $groupName,
        ]);

        $store->setPolydockVariableValue('lagoon_deploy_private_key', $deployKey, true);

        $this->info("✅ Store '{$store->name}' created successfully with ID: {$store->id}");

        return 0;
    }
}
