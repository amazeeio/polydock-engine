<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\PolydockAppInstance;
use FreedomtechHosting\PolydockApp\Enums\PolydockAppInstanceStatus;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PolydockAppInstanceStatusChanged
{
    use Dispatchable;
    use SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public PolydockAppInstance $appInstance,
        public ?PolydockAppInstanceStatus $previousStatus = null,
    ) {}
}
