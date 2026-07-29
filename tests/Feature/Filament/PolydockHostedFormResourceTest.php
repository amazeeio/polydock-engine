<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Filament\Admin\Resources\PolydockHostedFormResource\Pages\CreatePolydockHostedForm;
use App\Filament\Admin\Resources\PolydockHostedFormResource\Pages\ListPolydockHostedForms;
use App\Forms\GenericHostedForm;
use App\Models\PolydockHostedForm;
use App\Models\PolydockStore;
use App\Models\PolydockStoreApp;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PolydockHostedFormResourceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private PolydockStoreApp $storeApp;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
        Role::findOrCreate('super_admin', config('auth.defaults.guard'));
        $this->admin->assignRole('super_admin');

        $store = PolydockStore::factory()->create();
        $this->storeApp = PolydockStoreApp::factory()->create(['polydock_store_id' => $store->id]);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->admin);
    }

    #[Test]
    public function it_lists_hosted_forms()
    {
        PolydockHostedForm::create([
            'slug' => 'some-form',
            'form_class' => GenericHostedForm::class,
            'title' => 'Some Form',
        ]);

        Livewire::test(ListPolydockHostedForms::class)
            ->assertSuccessful()
            ->assertSee('Some Form');
    }

    #[Test]
    public function it_creates_a_hosted_form_with_allowed_apps()
    {
        Livewire::test(CreatePolydockHostedForm::class)
            ->fillForm([
                'title' => 'Partner Demo',
                'slug' => 'partner-demo',
                'form_class' => GenericHostedForm::class,
                'enabled' => true,
                'description' => 'A partner demo form',
                'storeApps' => [$this->storeApp->id],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $form = PolydockHostedForm::where('slug', 'partner-demo')->firstOrFail();
        $this->assertEquals(GenericHostedForm::class, $form->form_class);
        $this->assertEquals([$this->storeApp->id], $form->storeApps()->pluck('polydock_store_apps.id')->all());
    }
}
