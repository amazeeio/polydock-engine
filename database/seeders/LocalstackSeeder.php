<?php

namespace Database\Seeders;

use App\Enums\UserGroupRoleEnum;
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class LocalstackSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $fred = User::create([
            'first_name' => 'Localstack',
            'last_name' => 'Admin',
            'email' => 'administrator@localstack.com',
            'password' => Hash::make('password'),
        ]);

        $localstackTeam = UserGroup::create([
            'name' => 'Localstack Inc.',
        ]);

        // Attach the ownder
        $fred->groups()->attach($localstackTeam, [
            'role' => UserGroupRoleEnum::OWNER->value,
        ]);

        $deployKey = file_get_contents(config('polydock.lagoon_deploy_private_key_file'));

        // Create the stores
        $store = \App\Models\PolydockStore::create([
            'name' => 'Localstack Store',
            'status' => \App\Enums\PolydockStoreStatusEnum::PUBLIC,
            'listed_in_marketplace' => true,
            'lagoon_deploy_region_id_ext' => '2001',
            'lagoon_deploy_project_prefix' => 'localstack',
            'lagoon_deploy_organization_id_ext' => '1',
            'amazee_ai_backend_region_id_ext' => 666,
            'lagoon_deploy_group_name' => 'polydock-demo-apps',
        ]);
        $store->setPolydockVariableValue('lagoon_deploy_private_key', $deployKey, true);

        // Add webhook to both stores
        $webhookUrl = 'https://webhook.site/bbe9c2ef-bb18-4c13-8d40-14fb428c7b64';

        \App\Models\PolydockStoreWebhook::create([
            'polydock_store_id' => $store->id,
            'url' => $webhookUrl,
            'active' => true,
        ]);

        \App\Models\PolydockStoreApp::create([
            'polydock_store_id' => $store->id,
            'name' => 'Localstack amazee.io Node.js',
            'polydock_app_class' => \FreedomtechHosting\PolydockAppAmazeeioGeneric\PolydockApp::class,
            'description' => 'A simple amazee.io Node.js app deployed to Localstack',
            'author' => 'Bryan Gruneberg',
            'website' => 'https://freedomtech.hosting/',
            'support_email' => 'hello@freedomtech.hosting',
            'lagoon_deploy_git' => 'git@github.com:Freedomtech-Hosting/polydock-demo-node-simple.git',
            'lagoon_deploy_branch' => 'main',
            'status' => \App\Enums\PolydockStoreAppStatusEnum::AVAILABLE,
            'available_for_trials' => true,
            'target_unallocated_app_instances' => 0,
        ]);
    }
}
