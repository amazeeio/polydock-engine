<?php

namespace Tests\Feature\Database;

use App\Enums\PolydockStoreAppStatusEnum;
use App\Enums\PolydockStoreStatusEnum;
use App\Forms\DrupalAIDemoDrupalOrgForm;
use App\Forms\GenericHostedForm;
use App\Models\PolydockHostedForm;
use App\Models\PolydockStore;
use App\Models\PolydockStoreApp;
use App\Polydock\Apps\Generic\PolydockApp;
use Database\Seeders\HostedFormsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HostedFormsSeederTest extends TestCase
{
    use RefreshDatabase;

    private function makeStoreApp(PolydockStoreStatusEnum $storeStatus, bool $availableForTrials): PolydockStoreApp
    {
        $store = PolydockStore::create([
            'name' => 'Store '.uniqid(),
            'status' => $storeStatus,
            'listed_in_marketplace' => true,
            'lagoon_deploy_region_id_ext' => '1',
            'lagoon_deploy_project_prefix' => 'ft-'.uniqid(),
            'lagoon_deploy_organization_id_ext' => '123',
        ]);

        return PolydockStoreApp::create([
            'polydock_store_id' => $store->id,
            'name' => 'App '.uniqid(),
            'polydock_app_class' => PolydockApp::class,
            'lagoon_deploy_git' => 'git@github.com:example/app.git',
            'lagoon_deploy_branch' => 'main',
            'status' => PolydockStoreAppStatusEnum::AVAILABLE,
            'available_for_trials' => $availableForTrials,
            'support_email' => 'support@example.com',
            'author' => 'Test Author',
            'description' => 'Test Description',
        ]);
    }

    #[Test]
    public function it_seeds_both_forms_and_attaches_previously_offered_apps_to_the_drupal_org_form()
    {
        $publicTrialApp = $this->makeStoreApp(PolydockStoreStatusEnum::PUBLIC, true);
        $privateApp = $this->makeStoreApp(PolydockStoreStatusEnum::PRIVATE, true);
        $nonTrialApp = $this->makeStoreApp(PolydockStoreStatusEnum::PUBLIC, false);

        $this->seed(HostedFormsSeeder::class);

        $drupalOrg = PolydockHostedForm::where('slug', 'drupal-ai-demo')->firstOrFail();
        $this->assertEquals(DrupalAIDemoDrupalOrgForm::class, $drupalOrg->form_class);
        $this->assertTrue($drupalOrg->enabled);
        $this->assertEquals([$publicTrialApp->id], $drupalOrg->storeApps()->pluck('polydock_store_apps.id')->all());

        $partners = PolydockHostedForm::where('slug', 'drupal-ai-partners-demo')->firstOrFail();
        $this->assertEquals(GenericHostedForm::class, $partners->form_class);
        $this->assertCount(0, $partners->storeApps);

        // Idempotent: re-running does not duplicate records or pivot rows
        $this->seed(HostedFormsSeeder::class);
        $this->assertEquals(2, PolydockHostedForm::count());
        $this->assertEquals(1, $drupalOrg->storeApps()->count());
    }
}
