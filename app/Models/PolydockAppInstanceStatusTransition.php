<?php

declare(strict_types=1);

namespace App\Models;

use App\Polydock\Core\Enums\PolydockAppInstanceStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per status change of an app instance — the timing source of truth
 * for per-stage durations (the activity log deliberately does not record
 * status). Rows are immutable and cascade-delete with the instance.
 */
class PolydockAppInstanceStatusTransition extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'polydock_app_instance_id',
        'from_status',
        'to_status',
    ];

    protected $casts = [
        'from_status' => PolydockAppInstanceStatus::class,
        'to_status' => PolydockAppInstanceStatus::class,
        'created_at' => 'datetime',
    ];

    public function instance(): BelongsTo
    {
        return $this->belongsTo(PolydockAppInstance::class, 'polydock_app_instance_id');
    }
}
