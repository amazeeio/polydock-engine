<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs;

use App\Enums\PolydockStoreWebhookCallStatusEnum;
use App\Jobs\ProcessPolydockStoreWebhookCall;
use App\Models\PolydockStoreWebhook;
use App\Models\PolydockStoreWebhookCall;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProcessPolydockStoreWebhookCallTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The model's `created` boot hook auto-dispatches the job onto the
     * `webhooks` queue. Fake the bus while building fixtures so that
     * auto-dispatch does not interfere with the explicit `handle()` call
     * under test.
     */
    private function makeWebhookCall(string $url = 'https://example.test/hook'): PolydockStoreWebhookCall
    {
        Bus::fake();

        $webhook = PolydockStoreWebhook::factory()->create([
            'url' => $url,
            'active' => true,
        ]);

        return PolydockStoreWebhookCall::factory()->create([
            'polydock_store_webhook_id' => $webhook->id,
            'event' => 'app.created',
            'payload' => ['foo' => 'bar'],
            'status' => PolydockStoreWebhookCallStatusEnum::PENDING,
        ]);
    }

    public function test_successful_2xx_response_marks_call_as_success(): void
    {
        Http::fake([
            'https://example.test/*' => Http::response('all good', 200),
        ]);

        $call = $this->makeWebhookCall();

        (new ProcessPolydockStoreWebhookCall($call))->handle();

        $call->refresh();

        $this->assertSame(PolydockStoreWebhookCallStatusEnum::SUCCESS, $call->status);
        $this->assertSame('200', $call->response_code);
        $this->assertSame('all good', $call->response_body);
        $this->assertNotNull($call->processed_at);
        $this->assertNull($call->exception);

        Http::assertSent(function ($request) use ($call) {
            return $request->url() === 'https://example.test/hook'
                && $request->hasHeader('X-Polydock-Event', 'app.created')
                && $request->hasHeader('X-Polydock-Delivery', (string) $call->id)
                && $request->hasHeader('X-Polydock-Attempt');
        });
    }

    public function test_non_2xx_response_marks_call_as_failed_and_throws(): void
    {
        Http::fake([
            'https://example.test/*' => Http::response('server error', 500),
        ]);

        $call = $this->makeWebhookCall();

        try {
            (new ProcessPolydockStoreWebhookCall($call))->handle();
            $this->fail('Expected an exception to be thrown on a non-2xx response.');
        } catch (\Exception $e) {
            $this->assertStringContainsString('status code: 500', $e->getMessage());
        }

        $call->refresh();

        // The non-2xx branch first writes FAILED + response_code, then throws;
        // the catch block re-writes status. Because attempts() returns 1 when the
        // job is invoked directly (no queue job bound; 1 < tries=3), the catch
        // block sets PENDING.
        $this->assertSame(PolydockStoreWebhookCallStatusEnum::PENDING, $call->status);
        $this->assertSame('500', $call->response_code);
        $this->assertSame('server error', $call->response_body);
        $this->assertNotNull($call->processed_at);
        $this->assertNotNull($call->exception);
    }

    public function test_transport_exception_records_exception_and_rethrows(): void
    {
        Http::fake(function () {
            throw new ConnectionException('Connection timed out');
        });

        $call = $this->makeWebhookCall();

        try {
            (new ProcessPolydockStoreWebhookCall($call))->handle();
            $this->fail('Expected the transport exception to be re-thrown.');
        } catch (ConnectionException $e) {
            $this->assertSame('Connection timed out', $e->getMessage());
        }

        $call->refresh();

        // Characterization: invoked directly (no queue job bound), attempts()
        // returns 1, which is < tries (3), so the catch block sets the status
        // back to PENDING (not FAILED) and records the exception message.
        $this->assertSame(PolydockStoreWebhookCallStatusEnum::PENDING, $call->status);
        $this->assertSame('Connection timed out', $call->exception);
        $this->assertNotNull($call->processed_at);
        $this->assertNull($call->response_code);
    }
}
