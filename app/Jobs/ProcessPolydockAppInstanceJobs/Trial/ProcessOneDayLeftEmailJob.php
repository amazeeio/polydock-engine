<?php

namespace App\Jobs\ProcessPolydockAppInstanceJobs\Trial;

use App\Jobs\ProcessPolydockAppInstanceJobs\BaseJob;
use App\Mail\AppInstanceOneDayLeftMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ProcessOneDayLeftEmailJob extends BaseJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function handle()
    {
        $this->polydockJobStart();

        if (
            ! $this->appInstance->is_trial ||
            ! $this->appInstance->storeApp->send_one_day_left_email ||
            ! $this->appInstance->send_one_day_left_email_at ||
            ! $this->appInstance->send_one_day_left_email_at->isPast() ||
            $this->appInstance->one_day_left_email_sent
        ) {
            $this->appInstance->info('One day left email not sent', [
                'app_instance_id' => $this->appInstance->id,
                'is_trial' => $this->appInstance->is_trial,
                'send_one_day_left_email' => $this->appInstance->storeApp->send_one_day_left_email,
                'send_one_day_left_email_at' => $this->appInstance->send_one_day_left_email_at,
                'one_day_left_email_sent' => $this->appInstance->one_day_left_email_sent,
            ]);

            return;
        }

        if (! $this->appInstance->isTrialExpired()) {
            // Send email to owners
            Log::info('Sending one day left email to owners', [
                'app_instance_id' => $this->appInstance->id,
            ]);

            foreach ($this->appInstance->userGroup->owners as $owner) {
                $mail = Mail::to($owner->email);

                if (env('MAIL_CC_ALL', false)) {
                    $mail->cc(env('MAIL_CC_ALL'));
                }

                $this->appInstance->info('Sending one day left email to owner', [
                    'owner_id' => $owner->id,
                    'owner_email' => $owner->email,
                ]);

                Log::info('Sending one day left email to owner', [
                    'owner_id' => $owner->id,
                    'owner_email' => $owner->email,
                    'app_instance_id' => $this->appInstance->id,
                ]);

                $mail->queue(new AppInstanceOneDayLeftMail($this->appInstance, $owner));
            }
        } else {
            $this->appInstance->info('Trial expired, skipping one day left email but marking as sent', [
                'app_instance_id' => $this->appInstance->id,
            ]);

            Log::info('Trial expired, skipping one day left email but marking as sent', [
                'app_instance_id' => $this->appInstance->id,
                'is_trial' => $this->appInstance->is_trial,
                'send_one_day_left_email' => $this->appInstance->storeApp->send_one_day_left_email,
                'send_one_day_left_email_at' => $this->appInstance->send_one_day_left_email_at,
                'one_day_left_email_sent' => $this->appInstance->one_day_left_email_sent,
            ]);
        }

        // Update the sent flag
        $this->appInstance->update(['one_day_left_email_sent' => true]);

        $this->polydockJobDone();
    }
}
