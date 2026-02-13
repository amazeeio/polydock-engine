<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\PolydockAppInstanceCreatedWithNewStatus;
use App\Jobs\ProcessPolydockAppInstanceJobs\New\ProcessNewJob;
use Illuminate\Support\Facades\Log;

class ProcessNewPolydockAppInstance
{
    /**
     * Handle the event.
     */
    public function handle(PolydockAppInstanceCreatedWithNewStatus $event): void
    {
        Log::info('Dispatching ProcessPolydockAppInstanceJob via New ('.$event->appInstance->status->value.')', [
            'app_instance_id' => $event->appInstance->id,
            'store_app_id' => $event->appInstance->polydock_store_app_id,
            'store_app_name' => $event->appInstance->storeApp->name,
            'status' => $event->appInstance->status->value,
        ]);

        ProcessNewJob::dispatch($event->appInstance->id)
            ->onQueue('polydock-app-instance-processing-new');
    }
}
