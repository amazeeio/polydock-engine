<?php

namespace App\Events;

use App\Models\PolydockAppInstance;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PolydockAppInstanceCreatedWithNewStatus
{
    use Dispatchable, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public PolydockAppInstance $appInstance
    ) {}
} 