<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Models\User;
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
}
