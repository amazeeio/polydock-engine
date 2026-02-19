<?php

namespace Database\Seeders;

use App\Enums\UserGroupRoleEnum;
use App\Models\User;
use App\Models\UserGroup;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create Fred and his team
        $fred = User::create([
            'first_name' => 'Fred',
            'last_name' => 'Blogs',
            'email' => 'fred@example.com',
            'password' => Hash::make('password'),
        ]);

        $fredsTeam = UserGroup::create([
            'name' => 'Fracme Inc.',
        ]);

        // Make Fred the owner
        $fred->groups()->attach($fredsTeam, [
            'role' => UserGroupRoleEnum::OWNER->value,
        ]);

        // Create team members with predefined details
        $teamMembers = [
            [
                'first_name' => 'Alice',
                'last_name' => 'Smith',
                'email' => 'alice@example.com',
            ],
            [
                'first_name' => 'Bob',
                'last_name' => 'Jones',
                'email' => 'bob@example.com',
            ],
            [
                'first_name' => 'Carol',
                'last_name' => 'Wilson',
                'email' => 'carol@example.com',
            ],
        ];

        // Create and attach team members
        foreach ($teamMembers as $member) {
            $user = User::create([
                'first_name' => $member['first_name'],
                'last_name' => $member['last_name'],
                'email' => $member['email'],
                'password' => Hash::make('password'),
            ]);

            $user->groups()->attach($fredsTeam, [
                'role' => UserGroupRoleEnum::MEMBER->value,
            ]);
        }

        $deployKey = file_get_contents(config('polydock.lagoon_deploy_private_key_file'));

        // Create the stores
        $usaStore = \App\Models\PolydockStore::create([
            'name' => 'USA Store',
            'status' => \App\Enums\PolydockStoreStatusEnum::PUBLIC,
            'listed_in_marketplace' => true,
            'lagoon_deploy_region_id_ext' => '1',
            'lagoon_deploy_project_prefix' => 'ft-us',
            'lagoon_deploy_organization_id_ext' => '271',
            'amazee_ai_backend_region_id_ext' => 34,
            'lagoon_deploy_group_name' => 'polydock-demo-apps',
        ]);
        $usaStore->setPolydockVariableValue('lagoon_deploy_private_key', $deployKey, true);

        $switzerlandStore = \App\Models\PolydockStore::create([
            'name' => 'Switzerland Store',
            'status' => \App\Enums\PolydockStoreStatusEnum::PUBLIC,
            'listed_in_marketplace' => true,
            'lagoon_deploy_region_id_ext' => '1',
            'lagoon_deploy_project_prefix' => 'ft-ch',
            'lagoon_deploy_organization_id_ext' => '271',
            'amazee_ai_backend_region_id_ext' => 34,
            'lagoon_deploy_group_name' => 'polydock-demo-apps',
        ]);
        $switzerlandStore->setPolydockVariableValue('lagoon_deploy_private_key', $deployKey, true);

        // Add webhook to both stores
        $webhookUrl = 'https://webhook.site/bbe9c2ef-bb18-4c13-8d40-14fb428c7b64';

        \App\Models\PolydockStoreWebhook::create([
            'polydock_store_id' => $usaStore->id,
            'url' => $webhookUrl,
            'active' => true,
        ]);

        \App\Models\PolydockStoreWebhook::create([
            'polydock_store_id' => $switzerlandStore->id,
            'url' => $webhookUrl,
            'active' => true,
        ]);

        \App\Models\PolydockStoreApp::create([
            'polydock_store_id' => $usaStore->id,
            'name' => 'USA Simple amazee.io AI Node.js',
            'polydock_app_class' => \FreedomtechHosting\PolydockAppAmazeeioGeneric\PolydockAiApp::class,
            'description' => 'A simple amazee.io AI Node.js app deployed to the USA',
            'author' => 'Bryan Gruneberg',
            'website' => 'https://freedomtech.hosting/',
            'support_email' => 'hello@freedomtech.hosting',
            'lagoon_deploy_git' => 'git@github.com:Freedomtech-Hosting/polydock-demo-node-simple.git',
            'lagoon_deploy_branch' => 'main',
            'status' => \App\Enums\PolydockStoreAppStatusEnum::AVAILABLE,
            'available_for_trials' => true,
            'target_unallocated_app_instances' => 0,
        ]);

        \App\Models\PolydockStoreApp::create([
            'polydock_store_id' => $switzerlandStore->id,
            'name' => 'Switzerland Simple amazee.io Node.js',
            'polydock_app_class' => \FreedomtechHosting\PolydockAppAmazeeioGeneric\PolydockApp::class,
            'description' => 'A simple amazee.io Node.js app deployed to Switzerland',
            'author' => 'Bryan Gruneberg',
            'website' => 'https://freedomtech.hosting/',
            'support_email' => 'hello@freedomtech.hosting',
            'lagoon_deploy_git' => 'git@github.com:Freedomtech-Hosting/polydock-demo-node-simple.git',
            'lagoon_deploy_branch' => 'main',
            'status' => \App\Enums\PolydockStoreAppStatusEnum::AVAILABLE,
            'available_for_trials' => true,
            'target_unallocated_app_instances' => 0,
        ]);

        \App\Models\PolydockStoreApp::create([
            'polydock_store_id' => $usaStore->id,
            'name' => 'USA amazee.io AI - Categorize Pages',
            'polydock_app_class' => \FreedomtechHosting\PolydockAppAmazeeioGeneric\PolydockAiApp::class,
            'description' => 'A demo of amazee.io AI - Categorize Pages Functionality',
            'author' => 'Bryan Gruneberg',
            'website' => 'https://try.amazee.ai/',
            'support_email' => 'ai.support@amazee.io',
            'lagoon_deploy_git' => 'git@github.com:amazeeio-demos/polydock-ai-trial-drupal-cms-caegorize-page.git',
            'lagoon_deploy_branch' => 'main',
            'status' => \App\Enums\PolydockStoreAppStatusEnum::AVAILABLE,
            'available_for_trials' => true,
            'target_unallocated_app_instances' => 1,
            'lagoon_post_deploy_script' => '/app/.lagoon/scripts/polydock_post_deploy.sh',
            'lagoon_claim_script' => '/app/.lagoon/scripts/polydock_claim.sh',
        ]);

        \App\Models\PolydockStoreApp::create([
            'polydock_store_id' => $switzerlandStore->id,
            'name' => 'Switzerland amazee.io AI - Categorize Pages',
            'polydock_app_class' => \FreedomtechHosting\PolydockAppAmazeeioGeneric\PolydockAiApp::class,
            'description' => 'A demo of amazee.io AI - Categorize Pages Functionality',
            'author' => 'Bryan Gruneberg',
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

        \App\Models\PolydockStoreApp::create([
            'polydock_store_id' => $usaStore->id,
            'name' => 'USA amazee.io AI - CK Editor',
            'polydock_app_class' => \FreedomtechHosting\PolydockAppAmazeeioGeneric\PolydockAiApp::class,
            'description' => 'A demo of amazee.io AI - CK Editor Functionality',
            'author' => 'Bryan Gruneberg',
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

        \App\Models\PolydockStoreApp::create([
            'polydock_store_id' => $switzerlandStore->id,
            'name' => 'Switzerland amazee.io AI - CK Editor',
            'polydock_app_class' => \FreedomtechHosting\PolydockAppAmazeeioGeneric\PolydockAiApp::class,
            'description' => 'A demo of amazee.io AI - CK Editor Functionality',
            'author' => 'Bryan Gruneberg',
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

        // Add some example apps to each store
        \App\Models\PolydockStoreApp::factory()
            ->count(8)
            ->sequence(
                ['polydock_store_id' => $usaStore->id],
                ['polydock_store_id' => $switzerlandStore->id],
            )
            ->create();
    }
}
