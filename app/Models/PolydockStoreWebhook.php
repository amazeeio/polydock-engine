<?php

namespace App\Models;

use Database\Factories\PolydockStoreWebhookFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use RuntimeException;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property string $secret
 */
class PolydockStoreWebhook extends Model
{
    /** @use HasFactory<PolydockStoreWebhookFactory> */
    use HasFactory;

    use LogsActivity;

    protected $fillable = [
        'polydock_store_id',
        'url',
        'active',
        'include_sensitive_data',
    ];

    protected $casts = [
        'active' => 'boolean',
        'include_sensitive_data' => 'boolean',
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

        // Payloads can carry credentials and PII, and the HMAC signature is
        // only tamper-proof if the transport is encrypted — refuse plaintext
        // endpoints everywhere a webhook can be created or edited (Filament,
        // console command, raw model writes). Plain http is allowed only for
        // loopback addresses so local development receivers keep working.
        static::saving(function (self $webhook): void {
            if (! self::isAllowedUrl((string) $webhook->url)) {
                throw new RuntimeException(
                    "Webhook URL must use https:// (http:// is only allowed for localhost): {$webhook->url}"
                );
            }
        });
    }

    public static function isAllowedUrl(string $url): bool
    {
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return $scheme === 'https'
            || ($scheme === 'http' && in_array($host, ['localhost', '127.0.0.1'], true));
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
            throw new RuntimeException("Webhook {$this->id} has no signing secret; refusing to deliver.");
        }

        return 'sha256='.hash_hmac('sha256', $body, (string) $this->secret);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['url', 'active', 'include_sensitive_data'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /**
     * @return BelongsTo<PolydockStore, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(PolydockStore::class, 'polydock_store_id');
    }

    /**
     * Get the calls for this webhook
     *
     * @return HasMany<PolydockStoreWebhookCall, $this>
     */
    public function calls(): HasMany
    {
        return $this->hasMany(PolydockStoreWebhookCall::class);
    }
}
