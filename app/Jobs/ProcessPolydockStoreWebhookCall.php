<?php

namespace App\Jobs;

use App\Enums\PolydockStoreWebhookCallStatusEnum;
use App\Models\PolydockStoreWebhookCall;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProcessPolydockStoreWebhookCall implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [60, 60, 60]; // Retry every minute, up to 3 times
    }

    /**
     * Create a new job instance.
     */
    public function __construct(
        private PolydockStoreWebhookCall $webhookCall
    ) {}

    /**
     * Determine if the job should be retried.
     */
    public function retryUntil(): \DateTime
    {
        return now()->addMinutes(5); // Give up after 5 minutes total
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('Processing webhook call', [
            'webhook_call_id' => $this->webhookCall->id,
            'event' => $this->webhookCall->event,
            'attempt' => $this->attempts(),
        ]);

        try {
            // Mark as processing
            $this->webhookCall->update([
                'status' => PolydockStoreWebhookCallStatusEnum::PROCESSING,
                'attempt' => $this->attempts(),
            ]);

            // Make the HTTP request
            $response = Http::timeout(30)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'User-Agent' => 'PolydockWebhook/1.0',
                    'X-Polydock-Event' => $this->webhookCall->event,
                    'X-Polydock-Delivery' => (string) $this->webhookCall->id,
                    'X-Polydock-Attempt' => (string) $this->attempts(),
                ])
                ->post($this->webhookCall->webhook->url, $this->webhookCall->payload);

            // Update the call with the response
            $this->webhookCall->update([
                'status' => $response->successful()
                    ? PolydockStoreWebhookCallStatusEnum::SUCCESS
                    : PolydockStoreWebhookCallStatusEnum::FAILED,
                'processed_at' => now(),
                'response_code' => (string) $response->status(),
                'response_body' => $response->body(),
            ]);

            if (! $response->successful()) {
                Log::warning('Webhook call failed with non-2xx response', [
                    'webhook_call_id' => $this->webhookCall->id,
                    'status_code' => $response->status(),
                    'response' => $response->body(),
                    'attempt' => $this->attempts(),
                ]);

                // This will trigger a retry if we haven't exceeded tries
                throw new \Exception("Webhook call failed with status code: {$response->status()}");
            }

            Log::info('Webhook call processed successfully', [
                'webhook_call_id' => $this->webhookCall->id,
                'status_code' => $response->status(),
                'attempt' => $this->attempts(),
            ]);

        } catch (\Exception $e) {
            Log::error('Error processing webhook call', [
                'webhook_call_id' => $this->webhookCall->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'attempt' => $this->attempts(),
            ]);

            $this->webhookCall->update([
                'status' => $this->attempts() >= $this->tries
                    ? PolydockStoreWebhookCallStatusEnum::FAILED
                    : PolydockStoreWebhookCallStatusEnum::PENDING,
                'processed_at' => now(),
                'exception' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Webhook call job failed permanently', [
            'webhook_call_id' => $this->webhookCall->id,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
            'final_attempt' => $this->attempts(),
        ]);

        $this->webhookCall->update([
            'status' => PolydockStoreWebhookCallStatusEnum::FAILED,
            'processed_at' => now(),
            'exception' => $exception->getMessage(),
        ]);
    }
}
