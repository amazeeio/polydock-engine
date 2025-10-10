<?php

namespace App\Listeners;

use App\Enums\UserRemoteRegistrationStatusEnum;
use App\Events\PolydockAppInstanceStatusChanged;
use App\Jobs\ProcessPolydockAppInstanceJobs\Claim\ClaimJob;
use App\Jobs\ProcessPolydockAppInstanceJobs\Create\CreateJob;
use App\Jobs\ProcessPolydockAppInstanceJobs\Create\PostCreateJob;
use App\Jobs\ProcessPolydockAppInstanceJobs\Create\PreCreateJob;
use App\Jobs\ProcessPolydockAppInstanceJobs\Deploy\DeployJob;
use App\Jobs\ProcessPolydockAppInstanceJobs\Deploy\PostDeployJob;
use App\Jobs\ProcessPolydockAppInstanceJobs\Deploy\PreDeployJob;
use App\Jobs\ProcessPolydockAppInstanceJobs\ProgressToNextStageJob;
use App\Jobs\ProcessPolydockAppInstanceJobs\Remove\PostRemoveJob;
use App\Jobs\ProcessPolydockAppInstanceJobs\Remove\PreRemoveJob;
use App\Jobs\ProcessPolydockAppInstanceJobs\Remove\RemoveJob;
use App\Jobs\ProcessPolydockAppInstanceJobs\Upgrade\PostUpgradeJob;
use App\Jobs\ProcessPolydockAppInstanceJobs\Upgrade\PreUpgradeJob;
use App\Jobs\ProcessPolydockAppInstanceJobs\Upgrade\UpgradeJob;
use App\Mail\AppInstanceReadyMail;
use App\Models\PolydockAppInstance;
use FreedomtechHosting\PolydockApp\Enums\PolydockAppInstanceStatus;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ProcessPolydockAppInstanceStatusChange
{
    /**
     * Handle the event.
     */
    public function handle(PolydockAppInstanceStatusChanged $event): void
    {
        Log::info('Dispatching ProcessPolydockAppInstanceJob via StatusChanged ('.$event->appInstance->status->value.')', [
            'app_instance_id' => $event->appInstance->id,
            'store_app_id' => $event->appInstance->polydock_store_app_id,
            'store_app_name' => $event->appInstance->storeApp->name,
            'status' => $event->appInstance->status->value,
            'previous_status' => $event->previousStatus->value,
        ]);

        if (in_array($event->appInstance->status, PolydockAppInstance::$completedStatuses)) {

            if ($event->appInstance->remoteRegistration) {
                $appInstance = $event->appInstance;
                $event->appInstance->remoteRegistration->setResultValue('message', $appInstance->getStatusMessage());

                if ($appInstance->getKeyValue('lagoon-generate-app-admin-username')) {
                    $event->appInstance->remoteRegistration->setResultValue('app_admin_username', $appInstance->getKeyValue('lagoon-generate-app-admin-username'));
                }

                if ($appInstance->getKeyValue('lagoon-generate-app-admin-password')) {
                    $event->appInstance->remoteRegistration->setResultValue('app_admin_password', $appInstance->getKeyValue('lagoon-generate-app-admin-password'));
                }

                // Store user information in registration results
                if ($appInstance->getKeyValue('user-first-name')) {
                    $event->appInstance->remoteRegistration->setResultValue('user_first_name', $appInstance->getKeyValue('user-first-name'));
                }

                if ($appInstance->getKeyValue('user-last-name')) {
                    $event->appInstance->remoteRegistration->setResultValue('user_last_name', $appInstance->getKeyValue('user-last-name'));
                }

                if ($appInstance->getKeyValue('user-email')) {
                    $event->appInstance->remoteRegistration->setResultValue('user_email', $appInstance->getKeyValue('user-email'));
                }

                $event->appInstance->remoteRegistration->save();
            }

            ProgressToNextStageJob::dispatch($event->appInstance->id)
                ->onQueue('polydock-app-instance-processing-progress-to-next-stage');
        } else {
            $this->switchOnStatus($event);
        }
    }

    public function switchOnStatus(PolydockAppInstanceStatusChanged $event)
    {
        switch ($event->appInstance->status) {
            case PolydockAppInstanceStatus::PENDING_PRE_CREATE:
                Log::info('Dispatching PreCreateJob', [
                    'app_instance_id' => $event->appInstance->id,
                ]);

                PreCreateJob::dispatch($event->appInstance->id)
                    ->onQueue('polydock-app-instance-processing-create');
                break;
            case PolydockAppInstanceStatus::PENDING_CREATE:
                Log::info('Dispatching CreateJob', [
                    'app_instance_id' => $event->appInstance->id,
                ]);

                CreateJob::dispatch($event->appInstance->id)
                    ->onQueue('polydock-app-instance-processing-create');
                break;
            case PolydockAppInstanceStatus::PENDING_POST_CREATE:
                Log::info('Dispatching PostCreateJob', [
                    'app_instance_id' => $event->appInstance->id,
                ]);

                PostCreateJob::dispatch($event->appInstance->id)
                    ->onQueue('polydock-app-instance-processing-create');
                break;
            case PolydockAppInstanceStatus::PENDING_PRE_DEPLOY:
                Log::info('Dispatching PreDeployJob', [
                    'app_instance_id' => $event->appInstance->id,
                ]);

                PreDeployJob::dispatch($event->appInstance->id)
                    ->onQueue('polydock-app-instance-processing-deploy');
                break;
            case PolydockAppInstanceStatus::PENDING_DEPLOY:
                Log::info('Dispatching DeployJob', [
                    'app_instance_id' => $event->appInstance->id,
                ]);

                DeployJob::dispatch($event->appInstance->id)
                    ->onQueue('polydock-app-instance-processing-deploy');
                break;
            case PolydockAppInstanceStatus::PENDING_POST_DEPLOY:
                Log::info('Dispatching PostDeployJob', [
                    'app_instance_id' => $event->appInstance->id,
                ]);

                PostDeployJob::dispatch($event->appInstance->id)
                    ->onQueue('polydock-app-instance-processing-deploy');
                break;
            case PolydockAppInstanceStatus::PENDING_PRE_REMOVE:
                Log::info('Dispatching PreRemoveJob', [
                    'app_instance_id' => $event->appInstance->id,
                ]);

                PreRemoveJob::dispatch($event->appInstance->id)
                    ->onQueue('polydock-app-instance-processing-remove');
                break;
            case PolydockAppInstanceStatus::PENDING_REMOVE:
                Log::info('Dispatching RemoveJob', [
                    'app_instance_id' => $event->appInstance->id,
                ]);

                RemoveJob::dispatch($event->appInstance->id)
                    ->onQueue('polydock-app-instance-processing-remove');
                break;
            case PolydockAppInstanceStatus::PENDING_POST_REMOVE:
                Log::info('Dispatching PostRemoveJob', [
                    'app_instance_id' => $event->appInstance->id,
                ]);

                PostRemoveJob::dispatch($event->appInstance->id)
                    ->onQueue('polydock-app-instance-processing-remove');
                break;
            case PolydockAppInstanceStatus::PENDING_PRE_UPGRADE:
                Log::info('Dispatching PreUpgradeJob', [
                    'app_instance_id' => $event->appInstance->id,
                ]);

                PreUpgradeJob::dispatch($event->appInstance->id)
                    ->onQueue('polydock-app-instance-processing-upgrade');
                break;
            case PolydockAppInstanceStatus::PENDING_UPGRADE:
                Log::info('Dispatching UpgradeJob', [
                    'app_instance_id' => $event->appInstance->id,
                ]);

                UpgradeJob::dispatch($event->appInstance->id)
                    ->onQueue('polydock-app-instance-processing-upgrade');
                break;
            case PolydockAppInstanceStatus::PENDING_POST_UPGRADE:
                Log::info('Dispatching PostUpgradeJob', [
                    'app_instance_id' => $event->appInstance->id,
                ]);

                PostUpgradeJob::dispatch($event->appInstance->id)
                    ->onQueue('polydock-app-instance-processing-upgrade');
                break;
            case PolydockAppInstanceStatus::PENDING_POLYDOCK_CLAIM:
                Log::info('Dispatching ClaimJob', [
                    'app_instance_id' => $event->appInstance->id,
                ]);

                ClaimJob::dispatch($event->appInstance->id)
                    ->onQueue('polydock-app-instance-processing-claim');
                break;
            case PolydockAppInstanceStatus::RUNNING_HEALTHY_CLAIMED:
                if ($event->appInstance->remoteRegistration) {
                    $appInstance = $event->appInstance;
                    $remoteRegistration = $appInstance->remoteRegistration;
                    $remoteRegistration->setResultValue('message', 'Your trial is ready.');
                    $remoteRegistration->setResultValue('app_url', $appInstance->app_one_time_login_url);
                    $remoteRegistration->status = UserRemoteRegistrationStatusEnum::SUCCESS;
                    $remoteRegistration->save();

                    foreach ($appInstance->userGroup->owners as $owner) {
                        $mail = Mail::to($owner->email);

                        if (env('MAIL_CC_ALL', false)) {
                            $mail->cc(env('MAIL_CC_ALL'));
                        }

                        $appInstance->info('Sending ready email to owner', [
                            'owner_id' => $owner->id,
                            'owner_email' => $owner->email,
                        ]);

                        Log::info('Sending ready email to owner', [
                            'owner_id' => $owner->id,
                            'owner_email' => $owner->email,
                            'app_instance_id' => $appInstance->id,
                        ]);

                        $mail->queue(new AppInstanceReadyMail($appInstance, $owner));
                    }
                }
                break;
            default:
                Log::warning('No job to dispatch for status '.$event->appInstance->status->value);
        }
    }
}
