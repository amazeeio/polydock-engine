<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PolydockAppInstanceLog extends Model
{
    protected $fillable = [
        'polydock_app_instance_id',
        'type',
        'level',
        'message',
        'data'
    ];

    protected $casts = [
        'data' => 'array',
    ];

    /**
     * Get the instance that owns this log entry
     */
    public function instance(): BelongsTo
    {
        return $this->belongsTo(PolydockAppInstance::class, 'polydock_app_instance_id');
    }
} 