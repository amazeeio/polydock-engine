<?php

namespace Tests\Feature\Console\Commands;

use App\Models\PolydockAppInstance;
use App\Models\PolydockStore;
use App\Models\PolydockStoreApp;
use App\Polydock\Core\Enums\PolydockAppInstanceStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MarkStuckInstancesFailedCommandTest extends TestCase
{
    use RefreshDatabase;

    private function createInstance(
        PolydockStoreApp $storeApp,
        PolydockAppInstanceStatus $status,
        ?string $updatedAt = null,
    ): PolydockAppInstance {
        $instance = new PolydockAppInstance;
        $instance->uuid = 'test-'.uniqid();
        $instance->polydock_store_app_id = $storeApp->id;
        $instance->name = 'test-instance';
        $instance->status = $status;
        $instance->app_type = 'test_app_type';
        $instance->data = [];
        $instance->saveQuietly();

        if ($updatedAt !== null) {
            // Use query to bypass model events/casts updating the timestamp
            PolydockAppInstance::where('id', $instance->id)
                ->update(['updated_at' => $updatedAt]);
            $instance->refresh();
        }

        return $instance;
    }

    private function createStoreApp(): PolydockStoreApp
    {
        $store = PolydockStore::factory()->create();

        return PolydockStoreApp::factory()->create([
            'polydock_store_id' => $store->id,
        ]);
    }

    public function test_marks_stuck_instances_as_failed(): void
    {
        $storeApp = $this->createStoreApp();
        $stuckAt = now()->subMinutes(45)->toDateTimeString();

        $instance = $this->createInstance(
            $storeApp,
            PolydockAppInstanceStatus::PRE_CREATE_RUNNING,
            $stuckAt,
        );

        $this->artisan('polydock:mark-stuck-instances-failed', ['--threshold' => 30])
            ->assertSuccessful();

        $instance->refresh();
        $this->assertEquals(PolydockAppInstanceStatus::PRE_CREATE_FAILED, $instance->status);
        $this->assertStringContains('Automatically marked failed', $instance->status_message);
    }

    public function test_does_not_mark_instances_within_threshold(): void
    {
        $storeApp = $this->createStoreApp();
        $recentAt = now()->subMinutes(10)->toDateTimeString();

        $instance = $this->createInstance(
            $storeApp,
            PolydockAppInstanceStatus::CREATE_RUNNING,
            $recentAt,
        );

        $this->artisan('polydock:mark-stuck-instances-failed', ['--threshold' => 30])
            ->assertSuccessful();

        $instance->refresh();
        $this->assertEquals(PolydockAppInstanceStatus::CREATE_RUNNING, $instance->status);
    }

    public function test_dry_run_does_not_mutate(): void
    {
        $storeApp = $this->createStoreApp();
        $stuckAt = now()->subMinutes(45)->toDateTimeString();

        $instance = $this->createInstance(
            $storeApp,
            PolydockAppInstanceStatus::DEPLOY_RUNNING,
            $stuckAt,
        );

        $this->artisan('polydock:mark-stuck-instances-failed', [
            '--threshold' => 30,
            '--dry-run' => true,
        ])->assertSuccessful();

        $instance->refresh();
        $this->assertEquals(PolydockAppInstanceStatus::DEPLOY_RUNNING, $instance->status);
    }

    public function test_does_not_mark_non_intermediate_statuses(): void
    {
        $storeApp = $this->createStoreApp();
        $stuckAt = now()->subMinutes(45)->toDateTimeString();

        $instance = $this->createInstance(
            $storeApp,
            PolydockAppInstanceStatus::RUNNING_HEALTHY_CLAIMED,
            $stuckAt,
        );

        $this->artisan('polydock:mark-stuck-instances-failed', ['--threshold' => 30])
            ->assertSuccessful();

        $instance->refresh();
        $this->assertEquals(PolydockAppInstanceStatus::RUNNING_HEALTHY_CLAIMED, $instance->status);
    }

    #[DataProvider('statusTransitionProvider')]
    public function test_correct_status_transitions(
        PolydockAppInstanceStatus $from,
        PolydockAppInstanceStatus $expectedTo,
    ): void {
        $storeApp = $this->createStoreApp();
        $stuckAt = now()->subMinutes(45)->toDateTimeString();

        $instance = $this->createInstance($storeApp, $from, $stuckAt);

        $this->artisan('polydock:mark-stuck-instances-failed', ['--threshold' => 30])
            ->assertSuccessful();

        $instance->refresh();
        $this->assertEquals($expectedTo, $instance->status);
    }

    public static function statusTransitionProvider(): array
    {
        return [
            'new -> pre-create-failed' => [
                PolydockAppInstanceStatus::NEW,
                PolydockAppInstanceStatus::PRE_CREATE_FAILED,
            ],
            'pending-create -> create-failed' => [
                PolydockAppInstanceStatus::PENDING_CREATE,
                PolydockAppInstanceStatus::CREATE_FAILED,
            ],
            'post-create-running -> post-create-failed' => [
                PolydockAppInstanceStatus::POST_CREATE_RUNNING,
                PolydockAppInstanceStatus::POST_CREATE_FAILED,
            ],
            'deploy-running -> deploy-failed' => [
                PolydockAppInstanceStatus::DEPLOY_RUNNING,
                PolydockAppInstanceStatus::DEPLOY_FAILED,
            ],
            'post-deploy-completed -> post-deploy-failed' => [
                PolydockAppInstanceStatus::POST_DEPLOY_COMPLETED,
                PolydockAppInstanceStatus::POST_DEPLOY_FAILED,
            ],
            'polydock-claim-running -> polydock-claim-failed' => [
                PolydockAppInstanceStatus::POLYDOCK_CLAIM_RUNNING,
                PolydockAppInstanceStatus::POLYDOCK_CLAIM_FAILED,
            ],
        ];
    }

    public function test_logs_summary_not_per_instance(): void
    {
        Log::spy();

        $storeApp = $this->createStoreApp();
        $stuckAt = now()->subMinutes(45)->toDateTimeString();

        $this->createInstance($storeApp, PolydockAppInstanceStatus::CREATE_RUNNING, $stuckAt);
        $this->createInstance($storeApp, PolydockAppInstanceStatus::DEPLOY_RUNNING, $stuckAt);

        $this->artisan('polydock:mark-stuck-instances-failed', ['--threshold' => 30])
            ->assertSuccessful();

        Log::shouldHaveReceived('warning')
            ->withArgs(function (...$args) {
                $message = $args[0] ?? '';
                $context = $args[1] ?? [];

                return str_contains($message, 'marked instances as failed')
                    && ($context['count'] ?? null) === 2;
            })
            ->once();
    }

    private function assertStringContains(string $needle, ?string $haystack): void
    {
        $this->assertNotNull($haystack);
        $this->assertTrue(
            str_contains($haystack, $needle),
            "Failed asserting that '{$haystack}' contains '{$needle}'"
        );
    }
}
