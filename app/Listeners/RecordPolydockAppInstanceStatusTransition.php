<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\PolydockAppInstanceCreatedWithNewStatus;
use App\Events\PolydockAppInstanceStatusChanged;
use App\Models\PolydockAppInstanceStatusTransition;

/**
 * Persist every status change — including the creation-time NEW row — as a
 * transition record. Synchronous by design: it is a single INSERT, and row
 * ordering is what makes per-stage duration math trustworthy.
 */
class RecordPolydockAppInstanceStatusTransition
{
    public function handle(PolydockAppInstanceStatusChanged|PolydockAppInstanceCreatedWithNewStatus $event): void
    {
        PolydockAppInstanceStatusTransition::create([
            'polydock_app_instance_id' => $event->appInstance->id,
            // Null from_status marks the creation-time row.
            'from_status' => $event instanceof PolydockAppInstanceStatusChanged
                ? $event->previousStatus?->value
                : null,
            'to_status' => $event->appInstance->status->value,
        ]);
    }
}
