<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property string $secret
 */
class PolydockStoreWebhook extends Model
{
    use HasFactory;
    use LogsActivity;

    protected $fillable = [
        'polydock_store_id',
        'url',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    /**
     * Attributes hidden from array/JSON serialization. The signing secret must
     * never be exposed in API responses.
     *
     * @var list<string>
     */
    protected $hidden = [
        'secret',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $webhook): void {
            if (empty($webhook->secret)) {
                $webhook->secret = Str::random(40);
            }
        });
    }

    /**
     * Compute the HMAC-SHA256 signature for a request body, prefixed with the
     * algorithm name, for the `X-Polydock-Signature` header.
     */
    public function signPayload(string $body): string
    {
        // Refuse to emit a signature keyed on an empty secret — that would be a
        // deterministic, forgeable "sha256=" value. New rows get a secret via the
        // creating hook and existing rows are backfilled by migration, so this is
        // a defensive guard against raw inserts / unexpected null secrets.
        if (empty($this->secret)) {
            throw new \RuntimeException("Webhook {$this->id} has no signing secret; refusing to deliver.");
        }

        return 'sha256='.hash_hmac('sha256', $body, (string) $this->secret);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['url', 'active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(PolydockStore::class, 'polydock_store_id');
    }

    /**
     * Get the calls for this webhook
     */
    public function calls(): HasMany
    {
        return $this->hasMany(PolydockStoreWebhookCall::class);
    }
}
