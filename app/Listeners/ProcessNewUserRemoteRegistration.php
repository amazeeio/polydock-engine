<?php

namespace App\Listeners;

use App\Events\UserRemoteRegistrationCreated;
use App\Jobs\ProcessUserRemoteRegistration;
use Illuminate\Support\Facades\Log;
class ProcessNewUserRemoteRegistration
{
    /**
     * Handle the event.
     */
    public function handle(UserRemoteRegistrationCreated $event): void
    {
        Log::info('Processing new user remote registration', ['registration' => $event->registration->toArray()]);
        ProcessUserRemoteRegistration::dispatch($event->registration);
    }
} 