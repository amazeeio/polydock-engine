<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Enums\PolydockStoreAppStatusEnum;
use App\Filament\Admin\Resources\PolydockAppInstanceResource\Pages\CreatePolydockAppInstance;
use App\Models\PolydockStore;
use App\Models\PolydockStoreApp;
use App\Models\User;
use App\Polydock\Apps\AmazeeClaw\PolydockAmazeeClawAiApp;
use App\Polydock\Core\Attributes\PolydockAppInstanceFields;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use Throwable;

/**
 * Renders every registered admin resource's list and create page as a
 * super_admin. This is the regression net for Filament upgrades: form(),
 * table() and infolist() schemas are only evaluated at render time, so a
 * renamed builder method in a minor bump stays invisible until a page loads.
 *
 * Edit/view pages are excluded — they need a persisted record per resource,
 * which is fixture work these schemas don't need to be exercised.
 */
class ResourcePagesSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_resource_list_and_create_page_renders(): void
    {
        Queue::fake();

        Role::findOrCreate('super_admin', config('auth.defaults.guard'));
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($admin);

        $resources = Filament::getPanel('admin')->getResources();
        $this->assertNotEmpty($resources, 'No Filament resources were discovered.');

        // Collect rather than fail fast, so one broken schema does not hide
        // the rest — an upgrade usually breaks several resources at once.
        $failures = [];
        $rendered = 0;

        foreach ($resources as $resource) {
            foreach (['index', 'create'] as $pageName) {
                $page = $resource::getPages()[$pageName] ?? null;

                if ($page === null) {
                    continue;
                }

                try {
                    Livewire::test($page->getPage())->assertSuccessful();
                    $rendered++;
                } catch (Throwable $e) {
                    $failures[] = class_basename($resource).":{$pageName} — ".$e->getMessage();
                }
            }
        }

        $this->assertSame([], $failures, "Filament pages failed to render:\n".implode("\n", $failures));
        $this->assertGreaterThan(0, $rendered);
    }

    /**
     * The create-instance form hides its app-specific section until an app is
     * chosen, so the pass above never reaches that schema. Selecting an app
     * whose class declares instance fields is the only way to render it.
     */
    public function test_conditional_instance_config_section_renders_once_an_app_is_selected(): void
    {
        Queue::fake();

        Role::findOrCreate('super_admin', config('auth.defaults.guard'));
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');

        $store = PolydockStore::factory()->create();
        $storeApp = PolydockStoreApp::factory()
            ->availableForTrials()
            ->create([
                'polydock_store_id' => $store->id,
                'status' => PolydockStoreAppStatusEnum::AVAILABLE,
                'polydock_app_class' => PolydockAmazeeClawAiApp::class,
            ]);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($admin);

        Livewire::test(CreatePolydockAppInstance::class)
            ->fillForm(['trial_app' => $storeApp->uuid])
            ->assertSuccessful()
            ->assertFormFieldExists(PolydockAppInstanceFields::FIELD_PREFIX.'openclaw_default_model');
    }
}
