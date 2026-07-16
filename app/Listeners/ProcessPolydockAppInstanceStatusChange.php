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
use App\Jobs\ProcessPolydockAppInstanceJobs\Purge\ProcessProjectPurgeJob;
use App\Jobs\ProcessPolydockAppInstanceJobs\Remove\PostRemoveJob;
use App\Jobs\ProcessPolydockAppInstanceJobs\Remove\PreRemoveJob;
use App\Jobs\ProcessPolydockAppInstanceJobs\Remove\RemoveJob;
use App\Jobs\ProcessPolydockAppInstanceJobs\Upgrade\PostUpgradeJob;
use App\Jobs\ProcessPolydockAppInstanceJobs\Upgrade\PreUpgradeJob;
use App\Jobs\ProcessPolydockAppInstanceJobs\Upgrade\UpgradeJob;
use App\Mail\AppInstanceReadyMail;
use App\Models\PolydockAppInstance;
use App\Polydock\Core\Enums\PolydockAppInstanceStatus;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ProcessPolydockAppInstanceStatusChange
{
    /**
     * Handle the event.
     */
    public function handle(PolydockAppInstanceStatusChanged $event): void
    {
        Log::info(
            'Dispatching ProcessPolydockAppInstanceJob via StatusChanged ('.$event->appInstance->status->value.')',
            [
                'app_instance_id' => $event->appInstance->id,
                'store_app_id' => $event->appInstance->polydock_store_app_id,
                'store_app_name' => $event->appInstance->storeApp->name,
                'status' => $event->appInstance->status->value,
                'previous_status' => $event->previousStatus->value,
            ],
        );

        if (in_array($event->appInstance->status, PolydockAppInstance::$completedStatuses)) {
            if ($event->appInstance->remoteRegistration) {
                $appInstance = $event->appInstance;
                $event->appInstance->remoteRegistration->setResultValue('message', $appInstance->getStatusMessage());

                if ($appInstance->getKeyValue('lagoon-generate-app-admin-username')) {
                    $event->appInstance->remoteRegistration->setResultValue(
                        'app_admin_username',
                        $appInstance->getKeyValue('lagoon-generate-app-admin-username'),
                    );
                }

                if ($appInstance->getKeyValue('lagoon-generate-app-admin-password')) {
                    $event->appInstance->remoteRegistration->setResultValue(
                        'app_admin_password',
                        $appInstance->getKeyValue('lagoon-generate-app-admin-password'),
                    );
                }

                if ($appInstance->getKeyValue('app-admin-api-key')) {
                    $event->appInstance->remoteRegistration->setResultValue(
                        'app_admin_api_key',
                        $appInstance->getKeyValue('app-admin-api-key'),
                    );
                }

                // Store user information in registration results
                if ($appInstance->getKeyValue('user-first-name')) {
                    $event->appInstance->remoteRegistration->setResultValue(
                        'user_first_name',
                        $appInstance->getKeyValue('user-first-name'),
                    );
                }

                if ($appInstance->getKeyValue('user-last-name')) {
                    $event->appInstance->remoteRegistration->setResultValue(
                        'user_last_name',
                        $appInstance->getKeyValue('user-last-name'),
                    );
                }

                if ($appInstance->getKeyValue('user-email')) {
                    $event->appInstance->remoteRegistration->setResultValue(
                        'user_email',
                        $appInstance->getKeyValue('user-email'),
                    );
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
        $appInstance = $event->appInstance;

        // The two statuses with real behavior beyond dispatching a stage job.
        if ($appInstance->status === PolydockAppInstanceStatus::RUNNING_HEALTHY_CLAIMED) {
            $this->handleRunningHealthyClaimed($event);

            return;
        }

        if ($appInstance->status === PolydockAppInstanceStatus::REMOVED) {
            $this->handleRemoved($event);

            return;
        }

        // Every other handled status just dispatches its stage job on its queue.
        [$job, $queue] = match ($appInstance->status) {
            PolydockAppInstanceStatus::PENDING_PRE_CREATE => [PreCreateJob::class, 'polydock-app-instance-processing-create'],
            PolydockAppInstanceStatus::PENDING_CREATE => [CreateJob::class, 'polydock-app-instance-processing-create'],
            PolydockAppInstanceStatus::PENDING_POST_CREATE => [PostCreateJob::class, 'polydock-app-instance-processing-create'],
            PolydockAppInstanceStatus::PENDING_PRE_DEPLOY => [PreDeployJob::class, 'polydock-app-instance-processing-deploy'],
            PolydockAppInstanceStatus::PENDING_DEPLOY => [DeployJob::class, 'polydock-app-instance-processing-deploy'],
            PolydockAppInstanceStatus::PENDING_POST_DEPLOY => [PostDeployJob::class, 'polydock-app-instance-processing-deploy'],
            PolydockAppInstanceStatus::PENDING_PRE_REMOVE => [PreRemoveJob::class, 'polydock-app-instance-processing-remove'],
            PolydockAppInstanceStatus::PENDING_REMOVE => [RemoveJob::class, 'polydock-app-instance-processing-remove'],
            PolydockAppInstanceStatus::PENDING_POST_REMOVE => [PostRemoveJob::class, 'polydock-app-instance-processing-remove'],
            PolydockAppInstanceStatus::PENDING_PURGE => [ProcessProjectPurgeJob::class, 'polydock-app-instance-processing-remove'],
            PolydockAppInstanceStatus::PENDING_PRE_UPGRADE => [PreUpgradeJob::class, 'polydock-app-instance-processing-upgrade'],
            PolydockAppInstanceStatus::PENDING_UPGRADE => [UpgradeJob::class, 'polydock-app-instance-processing-upgrade'],
            PolydockAppInstanceStatus::PENDING_POST_UPGRADE => [PostUpgradeJob::class, 'polydock-app-instance-processing-upgrade'],
            PolydockAppInstanceStatus::PENDING_POLYDOCK_CLAIM => [ClaimJob::class, 'polydock-app-instance-processing-claim'],
            default => [null, null],
        };

        if ($job === null) {
            Log::warning('No job to dispatch for status '.$appInstance->status->value);

            return;
        }

        Log::info('Dispatching '.class_basename($job), [
            'app_instance_id' => $appInstance->id,
        ]);

        $job::dispatch($appInstance->id)->onQueue($queue);
    }

    private function handleRunningHealthyClaimed(PolydockAppInstanceStatusChanged $event): void
    {
        $manualHookRerun = data_get($event->appInstance->data, 'manual_hook_rerun');

        if (($manualHookRerun['hook'] ?? null) === 'claim') {
            $data = $event->appInstance->data ?? [];
            unset($data['manual_hook_rerun']);
            $event->appInstance->data = $data;
            $event->appInstance->saveQuietly();

            if (($manualHookRerun['skip_ready_notification'] ?? false) === true) {
                Log::info('Skipping ready email flow after manual claim rerun', [
                    'app_instance_id' => $event->appInstance->id,
                ]);

                return;
            }
        }

        if ($event->appInstance->remoteRegistration) {
            $appInstance = $event->appInstance;
            $remoteRegistration = $appInstance->remoteRegistration;
            $remoteRegistration->setResultValue('message', 'Your trial is ready.');
            $remoteRegistration->setResultValue('app_url', $appInstance->app_one_time_login_url);
            $remoteRegistration->status = UserRemoteRegistrationStatusEnum::SUCCESS;
            $remoteRegistration->save();

            foreach ($appInstance->userGroup->owners as $owner) {
                $mail = Mail::to($owner->email);

                if (config('mail.cc_all')) {
                    $mail->cc(config('mail.cc_all'));
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
    }

    private function handleRemoved(PolydockAppInstanceStatusChanged $event): void
    {
        // If force-purge was requested and this is not a retry coming back
        // from the purge job (which sets purge_last_attempted_at), immediately
        // transition to PENDING_PURGE. Retries are handled by the scheduled
        // DispatchProjectPurgeJobsCommand which enforces backoff.
        if ($event->appInstance->force_purge_requested_at !== null
            && $event->appInstance->purge_last_attempted_at === null) {
            Log::info('Force purge requested, immediately dispatching PENDING_PURGE', [
                'app_instance_id' => $event->appInstance->id,
            ]);

            $event->appInstance->purge_eligible_at = now();
            $event->appInstance->setStatus(
                PolydockAppInstanceStatus::PENDING_PURGE,
                'Force purge: skipping grace period',
            );
            $event->appInstance->save();
        }
    }
}
