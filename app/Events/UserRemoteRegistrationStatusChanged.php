<?php

namespace App\Events;

use App\Models\UserRemoteRegistration;
use App\Enums\UserRemoteRegistrationStatusEnum;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserRemoteRegistrationStatusChanged
{
    use Dispatchable, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public UserRemoteRegistration $registration,
        public UserRemoteRegistrationStatusEnum $previousStatus
    ) {}
} 