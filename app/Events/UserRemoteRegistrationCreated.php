<?php

namespace App\Events;

use App\Models\UserRemoteRegistration;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserRemoteRegistrationCreated
{
    use Dispatchable;
    use SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public UserRemoteRegistration $registration
    ) {}
}
