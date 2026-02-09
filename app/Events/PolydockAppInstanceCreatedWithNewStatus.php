<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\PolydockAppInstance;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PolydockAppInstanceCreatedWithNewStatus
{
    use Dispatchable;
    use SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public PolydockAppInstance $appInstance,
    ) {}
}
