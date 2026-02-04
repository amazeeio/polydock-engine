<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PolydockStoreWebhook extends Model
{
    use HasFactory;

    protected $fillable = [
        'polydock_store_id',
        'url',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

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