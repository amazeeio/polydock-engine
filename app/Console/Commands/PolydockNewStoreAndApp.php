<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class PolydockNewStoreAndApp extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'polydock:new-store-and-app
        {--name=}
        {--status=}
        {--listed_in_marketplace=}
        {--lagoon_deploy_region_id_ext=}
        {--lagoon_deploy_project_prefix=}
        {--lagoon_deploy_organization_id_ext=}
        {--amazee_ai_backend_region_id_ext=}
        {--lagoon_deploy_group_name=}
        {--app_name=}
        {--polydock_app_class=}
        {--description=}
        {--author=}
        {--website=}
        {--support_email=}
        {--lagoon_deploy_git=}
        {--lagoon_deploy_branch=}
        {--app_status=}
        {--available_for_trials=}
        {--target_unallocated_app_instances=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'This command creates a new Polydock Store and a new App within that store';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        try {
        $deployKey = file_get_contents(config('polydock.lagoon_deploy_private_key_file'));

        $name = $this->option('name') ?? $this->ask('Store name', 'Localstack Store');
        $status = $this->option('status') ?? $this->choice('Store status', ['private', 'public'], 'public');
        $listed = $this->option('listed_in_marketplace');
        if ($listed === null) {
            $listed = $this->choice('Listed in marketplace', ['true', 'false'], 'true');
        }
        $listed = filter_var($listed, FILTER_VALIDATE_BOOLEAN);
        $regionId = $this->option('lagoon_deploy_region_id_ext') ?? $this->ask('Lagoon deploy region ID ext', '2001');
        $projectPrefix = $this->option('lagoon_deploy_project_prefix') ?? $this->ask('Lagoon deploy project prefix', 'localstack');
        $orgId = $this->option('lagoon_deploy_organization_id_ext') ?? $this->ask('Lagoon deploy organization ID ext', '1');
        $aiRegionId = $this->option('amazee_ai_backend_region_id_ext') ?? $this->ask('Amazee AI backend region ID ext', 1);
        $groupName = $this->option('lagoon_deploy_group_name') ?? $this->ask('Lagoon deploy group name', 'polydock-demo-apps');

        $storeData = [
            'name' => $name,
            'status' => $status,
            'listed_in_marketplace' => $listed,
            'lagoon_deploy_region_id_ext' => intval($regionId),
            'lagoon_deploy_project_prefix' => $projectPrefix,
            'lagoon_deploy_organization_id_ext' => $orgId,
            'amazee_ai_backend_region_id_ext' => intval($aiRegionId),
            'lagoon_deploy_group_name' => $groupName,
            'lagoon_deploy_private_key' => $deployKey
        ];

        $storeData['lagoon_deploy_private_key'] = $deployKey;

        $store = \App\Models\PolydockStore::create($storeData);

        $appName = $this->option('app_name') ?? $this->ask('App name', 'Localstack amazee.io Node.js');
        $appClass = $this->option('polydock_app_class') ?? $this->ask('Polydock app class', 'FreedomtechHosting\PolydockAppAmazeeioGeneric\PolydockApp');
        $description = $this->option('description') ?? $this->ask('App description', 'A simple amazee.io Node.js app deployed to Localstack');
        $author = $this->option('author') ?? $this->ask('Author', 'Bryan Gruneberg');
        $website = $this->option('website') ?? $this->ask('Website', 'https://freedomtech.hosting/');
        $supportEmail = $this->option('support_email') ?? $this->ask('Support email', 'hello@freedomtech.hosting');
        $deployGit = $this->option('lagoon_deploy_git') ?? $this->ask('Lagoon deploy git', 'git@github.com:Freedomtech-Hosting/polydock-demo-node-simple.git');
        $deployBranch = $this->option('lagoon_deploy_branch') ?? $this->ask('Lagoon deploy branch', 'main');
        $appStatus = $this->option('app_status') ?? \App\Enums\PolydockStoreAppStatusEnum::AVAILABLE;
        $availableForTrials = $this->option('available_for_trials');
        if ($availableForTrials === null) {
            $availableForTrials = $this->choice('Available for trials', ['true', 'false'], 'true');
        }
        $availableForTrials = filter_var($availableForTrials, FILTER_VALIDATE_BOOLEAN);
        $targetUnallocated = $this->option('target_unallocated_app_instances') ?? $this->ask('Target unallocated app instances', 0);

        \App\Models\PolydockStoreApp::create([
            'polydock_store_id' => $store->id,
            'name' => $appName,
            'polydock_app_class' => $appClass,
            'description' => $description,
            'author' => $author,
            'website' => $website,
            'support_email' => $supportEmail,
            'lagoon_deploy_git' => $deployGit,
            'lagoon_deploy_branch' => $deployBranch,
            'status' => $appStatus,
            'available_for_trials' => $availableForTrials,
            'target_unallocated_app_instances' => $targetUnallocated,
        ]);

        $this->info('Polydock Store created successfully:');
        $this->line('  Store ID: ' . $store->id);
        $this->line('  Name: ' . $store->name);
        $this->line('  Status: ' . $store->status);
        $this->line('  Listed in Marketplace: ' . ($store->listed_in_marketplace ? 'Yes' : 'No'));
        $this->line('  Lagoon Deploy Region ID Ext: ' . $store->lagoon_deploy_region_id_ext);
        $this->line('  Lagoon Deploy Project Prefix: ' . $store->lagoon_deploy_project_prefix);
        $this->line('  Lagoon Deploy Organization ID Ext: ' . $store->lagoon_deploy_organization_id_ext);
        $this->line('  Amazee AI Backend Region ID Ext: ' . $store->amazee_ai_backend_region_id_ext);
        $this->line('  Lagoon Deploy Group Name: ' . $store->lagoon_deploy_group_name);

        $this->info('Polydock Store App created successfully:');
        $this->line('  Name: ' . $appName);
        $this->line('  Class: ' . $appClass);
        $this->line('  Description: ' . $description);
        $this->line('  Author: ' . $author);
        $this->line('  Website: ' . $website);
        $this->line('  Support Email: ' . $supportEmail);
        $this->line('  Lagoon Deploy Git: ' . $deployGit);
        $this->line('  Lagoon Deploy Branch: ' . $deployBranch);
        $this->line('  Status: ' . $appStatus);
        $this->line('  Available for Trials: ' . ($availableForTrials ? 'Yes' : 'No'));
        $this->line('  Target Unallocated App Instances: ' . $targetUnallocated);

    } catch (\Exception $e) {
            $this->error('Error creating Polydock Store or App: ' . $e->getMessage());
    }
}
}
