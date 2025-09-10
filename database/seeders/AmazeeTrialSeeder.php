<?php

namespace Database\Seeders;

use App\Enums\UserGroupRoleEnum;
use App\Models\User;
use App\Models\UserGroup;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AmazeeTrialSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if(! file_exists("amazeeai-trial.pass")) {
            throw new \Exception(" amazeeai-trial.pass file not found");
        }

        $password = trim(file_get_contents("amazeeai-trial.pass"));

        $admin = User::create([
            'first_name' => 'Admin',
            'last_name' => 'Istrator',
            'email' => 'admin@try.amazee.ai',
            'password' => Hash::make($password),
        ]);

        $adminTeam = UserGroup::create([
            'name' => "try.amazee.ai"
        ]);

        $admin->groups()->attach($adminTeam, [
            'role' => UserGroupRoleEnum::OWNER->value
        ]);

        $deployKey = file_get_contents(config('polydock.lagoon_deploy_private_key_file'));

        $usStore = \App\Models\PolydockStore::create([
            'name' => 'USA Store',
            'status' => \App\Enums\PolydockStoreStatusEnum::PUBLIC,
            'listed_in_marketplace' => true,
            'lagoon_deploy_region_id_ext' => '126',
            'lagoon_deploy_project_prefix' => 'ait-us',
            'lagoon_deploy_organization_id_ext' => '549',
            'lagoon_deploy_private_key' => $deployKey,
            'amazee_ai_backend_region_id_ext' => 68,
        ]);

        $chStore = \App\Models\PolydockStore::create([
            'name' => 'Switzerland Store',
            'status' => \App\Enums\PolydockStoreStatusEnum::PUBLIC,
            'listed_in_marketplace' => true,
            'lagoon_deploy_region_id_ext' => '131',
            'lagoon_deploy_project_prefix' => 'ait-ch',
            'lagoon_deploy_organization_id_ext' => '549',
            'lagoon_deploy_private_key' => $deployKey,
            'amazee_ai_backend_region_id_ext' => 34,
        ]);

        $auStore = \App\Models\PolydockStore::create([
            'name' => 'Australia Store',
            'status' => \App\Enums\PolydockStoreStatusEnum::PUBLIC,
            'listed_in_marketplace' => true,
            'lagoon_deploy_region_id_ext' => '132',
            'lagoon_deploy_project_prefix' => 'ait-au',
            'lagoon_deploy_organization_id_ext' => '549',
            'lagoon_deploy_private_key' => $deployKey,
            'amazee_ai_backend_region_id_ext' => 69,
        ]);

        $deStore = \App\Models\PolydockStore::create([
            'name' => 'Europe Store',
            'status' => \App\Enums\PolydockStoreStatusEnum::PUBLIC,
            'listed_in_marketplace' => true,
            'lagoon_deploy_region_id_ext' => '115',
            'lagoon_deploy_project_prefix' => 'ait-de',
            'lagoon_deploy_organization_id_ext' => '549',
            'lagoon_deploy_private_key' => $deployKey,
            'amazee_ai_backend_region_id_ext' => 67,
        ]);

        // Add webhook to both stores
        $webhookUrl = 'https://webhook.site/bbe9c2ef-bb18-4c13-8d40-14fb428c7b64';


        ////////////////////////////
        ///////////// USA //////////
        \App\Models\PolydockStoreWebhook::create([
            'polydock_store_id' => $usStore->id,
            'url' => $webhookUrl,
            'active' => true,
        ]);

        $this->getStoreAppCKEditor($usStore, 'USA');
        $this->getStoreAppCategorizePages($usStore, 'USA');
        $this->getStoreAppSearch($usStore, 'USA');
        $this->getStoreAppGeneric($usStore, 'USA');


        ////////////////////////////
        /////////// CH //////////
        \App\Models\PolydockStoreWebhook::create([
            'polydock_store_id' => $chStore->id,
            'url' => $webhookUrl,
            'active' => true,
        ]);

        $this->getStoreAppCKEditor($chStore, 'CH');
        $this->getStoreAppCategorizePages($chStore, 'CH');
        $this->getStoreAppSearch($chStore, 'CH');
        $this->getStoreAppGeneric($chStore, 'CH');

        ////////////////////////////
        /////////// AU //////////
        \App\Models\PolydockStoreWebhook::create([
            'polydock_store_id' => $auStore->id,
            'url' => $webhookUrl,
            'active' => true,
        ]);

        $this->getStoreAppCKEditor($auStore, 'AU');
        $this->getStoreAppCategorizePages($auStore, 'AU');
        $this->getStoreAppSearch($auStore, 'AU');
        $this->getStoreAppGeneric($auStore, 'AU');

        ////////////////////////////
        /////////// DE //////////
        \App\Models\PolydockStoreWebhook::create([
            'polydock_store_id' => $deStore->id,
            'url' => $webhookUrl,
            'active' => true,
        ]);

        $this->getStoreAppCKEditor($deStore, 'DE');
        $this->getStoreAppCategorizePages($deStore, 'DE');
        $this->getStoreAppSearch($deStore, 'DE');
        $this->getStoreAppGeneric($deStore, 'DE');
    }

    public function getStoreAppCKEditor($store, $namePrefix)
    {
        \App\Models\PolydockStoreApp::create([
            'polydock_store_id' => $store->id,
            'name' => $namePrefix . ' amazee.io AI - CK Editor',
            'polydock_app_class' => 'amazeeio\PolydockAppAmazeeioGeneric\PolydockAiApp',
            'description' => 'Drupal AI - CK Editor',
            'author' => 'amazee.io',
            'website' => 'https://try.amazee.ai/',
            'support_email' => 'ai.support@amazee.io',
            'lagoon_deploy_git' => 'git@github.com:amazeeio-demos/polydock-ai-trial-drupal-cms-ck-editor.git',
            'lagoon_deploy_branch' => 'main',
            'status' => \App\Enums\PolydockStoreAppStatusEnum::AVAILABLE,
            'available_for_trials' => true,
            'target_unallocated_app_instances' => 0,
            'lagoon_post_deploy_script' => '/app/.lagoon/scripts/polydock_post_deploy.sh',
            'lagoon_claim_script' => '/app/.lagoon/scripts/polydock_claim.sh',
        ]);
    }

    public function getStoreAppCategorizePages($store, $namePrefix)
    {
        \App\Models\PolydockStoreApp::create([
            'polydock_store_id' => $store->id,
            'name' => $namePrefix . ' amazee.io AI - Categorize Pages',
            'polydock_app_class' => 'amazeeio\PolydockAppAmazeeioGeneric\PolydockAiApp',
            'description' => 'Drupal AI - Categorize Pages',
            'author' => 'amazee.io',
            'website' => 'https://try.amazee.ai/',
            'support_email' => 'ai.support@amazee.io',
            'lagoon_deploy_git' => 'git@github.com:amazeeio-demos/polydock-ai-trial-drupal-cms-caegorize-page.git',
            'lagoon_deploy_branch' => 'main',
            'status' => \App\Enums\PolydockStoreAppStatusEnum::AVAILABLE,
            'available_for_trials' => true,
            'target_unallocated_app_instances' => 0,
            'lagoon_post_deploy_script' => '/app/.lagoon/scripts/polydock_post_deploy.sh',
            'lagoon_claim_script' => '/app/.lagoon/scripts/polydock_claim.sh',
        ]);
    }

    public function getStoreAppSearch($store, $namePrefix)
    {
        \App\Models\PolydockStoreApp::create([
            'polydock_store_id' => $store->id,
            'name' => $namePrefix . ' amazee.io AI - Search',
            'polydock_app_class' => 'amazeeio\PolydockAppAmazeeioGeneric\PolydockAiApp',
            'description' => 'Drupal AI - Search',
            'author' => 'amazee.io',
            'website' => 'https://try.amazee.ai/',
            'support_email' => 'ai.support@amazee.io',
            'lagoon_deploy_git' => 'git@github.com:amazeeio-demos/polydock-ai-trial-drupal-cms-search.git',
            'lagoon_deploy_branch' => 'main',
            'status' => \App\Enums\PolydockStoreAppStatusEnum::AVAILABLE,
            'available_for_trials' => true,
            'target_unallocated_app_instances' => 0,
            'lagoon_post_deploy_script' => '/app/.lagoon/scripts/polydock_post_deploy.sh',
            'lagoon_claim_script' => '/app/.lagoon/scripts/polydock_claim.sh',
        ]);
    }

    public function getStoreAppGeneric($store, $namePrefix)
    {
        \App\Models\PolydockStoreApp::create([
            'polydock_store_id' => $store->id,
            'name' => $namePrefix . ' amazee.io AI - Generic',
            'polydock_app_class' => 'amazeeio\PolydockAppAmazeeioGeneric\PolydockAiApp',
            'description' => 'Generic amazee.io AI App',
            'author' => 'amazee.io',
            'website' => 'https://try.amazee.ai/',
            'support_email' => 'ai.support@amazee.io',
            'lagoon_deploy_git' => 'git@github.com:amazeeio-demos/polydock-ai-trial-generic.git',
            'lagoon_deploy_branch' => 'main',
            'status' => \App\Enums\PolydockStoreAppStatusEnum::AVAILABLE,
            'available_for_trials' => true,
            'target_unallocated_app_instances' => 0,
            'lagoon_post_deploy_script' => '/app/.lagoon/scripts/polydock_post_deploy.sh',
            'lagoon_claim_script' => '/app/.lagoon/scripts/polydock_claim.sh',
        ]);
    }
}
