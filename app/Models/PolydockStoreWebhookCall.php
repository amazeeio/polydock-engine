<?php

namespace App\Models;

use App\Enums\PolydockStoreWebhookCallStatusEnum;
use App\Jobs\ProcessPolydockStoreWebhookCall;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log;

class PolydockStoreWebhookCall extends Model
{
    use HasFactory;

    protected $fillable = [
        'polydock_store_webhook_id',
        'event',
        'payload',
        'status',
        'attempt',
        'processed_at',
        'response_code',
        'response_body',
        'exception',
    ];

    protected $casts = [
        'payload' => 'array',
        'processed_at' => 'datetime',
        'attempt' => 'integer',
        'status' => PolydockStoreWebhookCallStatusEnum::class,
    ];

    /**
     * Boot the model.
     */
    #[\Override]
    protected static function boot()
    {
        parent::boot();

        static::created(function ($webhookCall) {
            Log::info('Queuing webhook call for processing', [
                'webhook_call_id' => $webhookCall->id,
                'event' => $webhookCall->event,
            ]);

            ProcessPolydockStoreWebhookCall::dispatch($webhookCall)
                ->onQueue('webhooks');
        });
    }

    /**
     * Get the webhook that this call belongs to
     */
    public function webhook(): BelongsTo
    {
        return $this->belongsTo(PolydockStoreWebhook::class, 'polydock_store_webhook_id');
    }
}
