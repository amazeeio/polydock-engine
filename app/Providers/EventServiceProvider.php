<?php

declare(strict_types=1);

namespace App\Providers;

use App\Events\PolydockAppInstanceCreatedWithNewStatus;
use App\Events\PolydockAppInstanceStatusChanged;
use App\Events\UserRemoteRegistrationCreated;
use App\Events\UserRemoteRegistrationStatusChanged;
use App\Listeners\CreateWebhookCallForAppInstanceStatusChanged;
use App\Listeners\CreateWebhookCallForRegistrationStatusSuccessOrFailed;
use App\Listeners\ProcessNewPolydockAppInstance;
use App\Listeners\ProcessNewUserRemoteRegistration;
use App\Listeners\ProcessPolydockAppInstanceStatusChange;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string|string>>
     */
    protected $listen = [
        UserRemoteRegistrationCreated::class => [
            ProcessNewUserRemoteRegistration::class,
        ],
        UserRemoteRegistrationStatusChanged::class => [
            CreateWebhookCallForRegistrationStatusSuccessOrFailed::class,
        ],
        PolydockAppInstanceCreatedWithNewStatus::class => [
            ProcessNewPolydockAppInstance::class,
        ],
        PolydockAppInstanceStatusChanged::class => [
            CreateWebhookCallForAppInstanceStatusChanged::class,
            ProcessPolydockAppInstanceStatusChange::class,
        ],
    ];

    /**
     * Register any events for your application.
     */
    #[\Override]
    public function boot()
    {
        //
    }
}
