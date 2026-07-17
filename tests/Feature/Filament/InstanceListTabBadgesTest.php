<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Filament\Admin\Resources\PolydockAppInstanceResource\Pages\ListPolydockAppInstances;
use App\Models\PolydockAppInstance;
use App\Models\PolydockStore;
use App\Models\PolydockStoreApp;
use App\Models\User;
use App\Polydock\Core\Enums\PolydockAppInstanceStatus;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The tab badges are now derived from ONE grouped status-count query — this
 * pins that the derived numbers match what per-scope counts produced.
 */
class InstanceListTabBadgesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private PolydockStoreApp $storeApp;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        $this->admin = User::factory()->create();
        Role::findOrCreate('super_admin', config('auth.defaults.guard'));
        $this->admin->assignRole('super_admin');

        $store = PolydockStore::factory()->create();
        $this->storeApp = PolydockStoreApp::factory()->create(['polydock_store_id' => $store->id]);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function makeInstance(PolydockAppInstanceStatus $status): void
    {
        $instance = new PolydockAppInstance;
        $instance->fill([
            'polydock_store_app_id' => $this->storeApp->id,
            'name' => 'badge-'.Str::random(6),
            'app_type' => 'test-app',
            'status' => $status,
        ]);
        $instance->uuid = (string) Str::uuid();
        $instance->saveQuietly();
    }

    public function test_tab_badges_reflect_status_counts(): void
    {
        // 2 claimed, 1 unclaimed, 2 mid-pipeline, 1 removed => 6 total, 5 active.
        // NEW is deliberately included: it appears both explicitly and inside
        // $stageCreateStatuses in the in-progress list, and must be counted once.
        $this->makeInstance(PolydockAppInstanceStatus::RUNNING_HEALTHY_CLAIMED);
        $this->makeInstance(PolydockAppInstanceStatus::RUNNING_HEALTHY_CLAIMED);
        $this->makeInstance(PolydockAppInstanceStatus::RUNNING_HEALTHY_UNCLAIMED);
        $this->makeInstance(PolydockAppInstanceStatus::PENDING_DEPLOY);
        $this->makeInstance(PolydockAppInstanceStatus::NEW);
        $this->makeInstance(PolydockAppInstanceStatus::REMOVED);

        $this->actingAs($this->admin);
        Livewire::test(ListPolydockAppInstances::class)->assertSuccessful();

        $page = new ListPolydockAppInstances;
        $badges = collect($page->getTabs())
            ->map(fn ($tab) => $tab->getBadge());

        $this->assertSame(5, $badges['active']);
        $this->assertSame(2, $badges['in_progress']);
        $this->assertSame(2, $badges['healthy_claimed']);
        $this->assertSame(1, $badges['healthy_unclaimed']);
        $this->assertSame(1, $badges['removed']);
        $this->assertSame(6, $badges['all']);
    }

    public function test_tab_badges_are_zero_on_an_empty_table(): void
    {
        $this->actingAs($this->admin);

        $badges = collect((new ListPolydockAppInstances)->getTabs())
            ->map(fn ($tab) => $tab->getBadge());

        $this->assertSame(0, $badges['active']);
        $this->assertSame(0, $badges['all']);
        $this->assertSame(0, $badges['healthy_claimed']);
    }
}
