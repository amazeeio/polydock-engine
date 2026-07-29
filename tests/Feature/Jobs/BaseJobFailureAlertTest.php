<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs;

use App\Jobs\ProcessPolydockAppInstanceJobs\Claim\ClaimJob;
use App\Models\PolydockAppInstance;
use App\Models\PolydockStore;
use App\Models\PolydockStoreApp;
use App\Polydock\Core\Enums\PolydockAppInstanceStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Spatie\SlackAlerts\Jobs\SendToSlackChannelJob;
use Tests\TestCase;

/**
 * BaseJob::failed() must page Slack on lifecycle-job failures — once per job
 * class per 5 minutes — and must never throw, even when the webhook is unset
 * or the instance is gone.
 */
class BaseJobFailureAlertTest extends TestCase
{
    use RefreshDatabase;

    private function makeInstance(): PolydockAppInstance
    {
        $storeApp = PolydockStoreApp::factory()->create([
            'polydock_store_id' => PolydockStore::factory()->create()->id,
        ]);

        $instance = new PolydockAppInstance;
        $instance->polydock_store_app_id = $storeApp->id;
        $instance->name = 'alert-test';
        $instance->app_type = 'test-app';
        $instance->status = PolydockAppInstanceStatus::PENDING_POLYDOCK_CLAIM;
        $instance->saveQuietly();

        return $instance;
    }

    public function test_failure_sends_a_slack_alert_when_webhook_configured(): void
    {
        Bus::fake();
        config(['slack-alerts.webhook_urls.default' => 'https://hooks.slack.test/x']);

        $job = new ClaimJob($this->makeInstance()->id);
        $job->failed(new \RuntimeException('boom'));

        Bus::assertDispatched(SendToSlackChannelJob::class);
    }

    public function test_no_alert_without_a_webhook(): void
    {
        Bus::fake();
        config(['slack-alerts.webhook_urls.default' => null]);

        $job = new ClaimJob($this->makeInstance()->id);
        $job->failed(new \RuntimeException('boom'));

        Bus::assertNotDispatched(SendToSlackChannelJob::class);
    }

    public function test_alerts_are_deduplicated_per_job_class(): void
    {
        Bus::fake();
        config(['slack-alerts.webhook_urls.default' => 'https://hooks.slack.test/x']);

        $first = new ClaimJob($this->makeInstance()->id);
        $second = new ClaimJob($this->makeInstance()->id);

        $first->failed(new \RuntimeException('boom one'));
        $second->failed(new \RuntimeException('boom two'));

        Bus::assertDispatchedTimes(SendToSlackChannelJob::class, 1);
    }

    public function test_a_broken_dedup_cache_fails_open_and_still_alerts(): void
    {
        Bus::fake();
        config(['slack-alerts.webhook_urls.default' => 'https://hooks.slack.test/x']);

        Cache::shouldReceive('add')->once()->andThrow(new \RuntimeException('cache store down'));

        $job = new ClaimJob($this->makeInstance()->id);
        $job->failed(new \RuntimeException('boom'));

        // The dedup store being down must not silence the page.
        Bus::assertDispatched(SendToSlackChannelJob::class);
    }

    public function test_a_database_outage_does_not_silence_the_alert(): void
    {
        Bus::fake();
        config(['slack-alerts.webhook_urls.default' => 'https://hooks.slack.test/x']);

        $job = new ClaimJob(1);

        // Simulate the DB being unreachable for the breadcrumb lookup: with
        // the instances table gone, the find() inside failed() throws — the
        // alert block must be independent of it.
        Schema::drop('polydock_app_instances');

        $job->failed(new \RuntimeException('boom'));

        Bus::assertDispatched(SendToSlackChannelJob::class);
    }

    public function test_failed_handler_never_throws_for_a_missing_instance(): void
    {
        Bus::fake();
        config(['slack-alerts.webhook_urls.default' => 'https://hooks.slack.test/x']);

        $job = new ClaimJob(999999); // nonexistent instance id
        $job->failed(new \RuntimeException('boom'));

        // Reaching here without an exception is the assertion; the alert
        // itself still fires (instance id is included as-is).
        Bus::assertDispatched(SendToSlackChannelJob::class);
    }
}
