<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\PolydockAppInstance;
use App\Models\PolydockAppInstanceLog;
use App\Models\PolydockStore;
use App\Models\PolydockStoreApp;
use App\Polydock\Core\Enums\PolydockAppInstanceStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PruneInstanceLogsCommandTest extends TestCase
{
    use RefreshDatabase;

    private PolydockAppInstance $instance;

    protected function setUp(): void
    {
        parent::setUp();

        $storeApp = PolydockStoreApp::factory()->create([
            'polydock_store_id' => PolydockStore::factory()->create()->id,
        ]);

        $instance = new PolydockAppInstance;
        $instance->polydock_store_app_id = $storeApp->id;
        $instance->name = 'prune-test';
        $instance->app_type = 'test-app';
        $instance->status = PolydockAppInstanceStatus::RUNNING_HEALTHY_CLAIMED;
        $instance->saveQuietly();
        $this->instance = $instance;
    }

    private function seedLog(int $daysOld): PolydockAppInstanceLog
    {
        $log = $this->instance->logs()->create([
            'type' => 'model_log',
            'level' => 'debug',
            'message' => "log {$daysOld}d old",
            'data' => [],
        ]);
        $log->created_at = now()->subDays($daysOld);
        $log->saveQuietly();

        return $log;
    }

    public function test_prunes_only_rows_older_than_retention(): void
    {
        $old = $this->seedLog(10);
        $fresh = $this->seedLog(0);

        $this->artisan('polydock:prune-instance-logs')->assertSuccessful();

        $this->assertDatabaseMissing($old->getTable(), ['id' => $old->id]);
        $this->assertDatabaseHas($fresh->getTable(), ['id' => $fresh->id]);
    }

    public function test_dry_run_reports_without_deleting(): void
    {
        $old = $this->seedLog(10);

        $this->artisan('polydock:prune-instance-logs --dry-run')
            ->expectsOutputToContain('Would delete 1')
            ->assertSuccessful();

        $this->assertDatabaseHas($old->getTable(), ['id' => $old->id]);
    }

    public function test_days_option_overrides_retention(): void
    {
        $old = $this->seedLog(10);

        $this->artisan('polydock:prune-instance-logs --days=30')->assertSuccessful();

        $this->assertDatabaseHas($old->getTable(), ['id' => $old->id]);
    }
}
