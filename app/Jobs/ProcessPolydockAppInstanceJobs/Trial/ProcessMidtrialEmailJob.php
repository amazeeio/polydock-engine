<?php

namespace App\Jobs\ProcessPolydockAppInstanceJobs\Trial;

use App\Jobs\ProcessPolydockAppInstanceJobs\BaseJob;
use App\Mail\AppInstanceMidtrialMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ProcessMidtrialEmailJob extends BaseJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function handle()
    {
        $this->polydockJobStart();

        if (
            ! $this->appInstance->is_trial
            || ! $this->appInstance->storeApp->send_midtrial_email
            || ! $this->appInstance->send_midtrial_email_at
            || ! $this->appInstance->send_midtrial_email_at->isPast()
            || $this->appInstance->midtrial_email_sent
        ) {
            $this->appInstance->info('Midtrial email not sent', [
                'app_instance_id' => $this->appInstance->id,
                'is_trial' => $this->appInstance->is_trial,
                'send_midtrial_email' => $this->appInstance->storeApp->send_midtrial_email,
                'send_midtrial_email_at' => $this->appInstance->send_midtrial_email_at,
                'midtrial_email_sent' => $this->appInstance->midtrial_email_sent,
            ]);

            Log::info('Midtrial email not sent', [
                'app_instance_id' => $this->appInstance->id,
                'is_trial' => $this->appInstance->is_trial,
                'send_midtrial_email' => $this->appInstance->storeApp->send_midtrial_email,
                'send_midtrial_email_at' => $this->appInstance->send_midtrial_email_at,
                'midtrial_email_sent' => $this->appInstance->midtrial_email_sent,
            ]);

            return;
        }

        if (! $this->appInstance->isTrialExpired()) {
            // Send email to owners
            $this->appInstance->info('Sending midtrial email to owners', [
                'app_instance_id' => $this->appInstance->id,
            ]);

            Log::info('Sending midtrial email to owners', [
                'app_instance_id' => $this->appInstance->id,
            ]);

            foreach ($this->appInstance->userGroup->owners as $owner) {
                $mail = Mail::to($owner->email);

                if (env('MAIL_CC_ALL', false)) {
                    $mail->cc(env('MAIL_CC_ALL'));
                }

                $this->appInstance->info('Sending midtrial email to owner', [
                    'owner_id' => $owner->id,
                    'owner_email' => $owner->email,
                ]);

                Log::info('Sending midtrial email to owner', [
                    'owner_id' => $owner->id,
                    'owner_email' => $owner->email,
                    'app_instance_id' => $this->appInstance->id,
                ]);

                $mail->queue(new AppInstanceMidtrialMail($this->appInstance, $owner));
            }
        } else {
            $this->appInstance->info('Trial expired, skipping midtrial email but marking as sent', [
                'app_instance_id' => $this->appInstance->id,
            ]);

            Log::info('Trial expired, skipping midtrial email but marking as sent', [
                'app_instance_id' => $this->appInstance->id,
                'is_trial' => $this->appInstance->is_trial,
                'send_midtrial_email' => $this->appInstance->storeApp->send_midtrial_email,
                'send_midtrial_email_at' => $this->appInstance->send_midtrial_email_at,
                'midtrial_email_sent' => $this->appInstance->midtrial_email_sent,
            ]);
        }

        // Update the sent flag
        $this->appInstance->update(['midtrial_email_sent' => true]);

        $this->polydockJobDone();
    }
}
