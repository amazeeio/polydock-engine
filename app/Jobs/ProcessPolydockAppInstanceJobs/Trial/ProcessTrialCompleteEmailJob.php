<?php

namespace App\Jobs\ProcessPolydockAppInstanceJobs\Trial;

use App\Jobs\ProcessPolydockAppInstanceJobs\BaseJob;
use App\Mail\AppInstanceTrialCompleteMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class ProcessTrialCompleteEmailJob extends BaseJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle()
    {   
        $this->polydockJobStart();

        if (!$this->appInstance->is_trial || 
            !$this->appInstance->storeApp->send_trial_complete_email ||
            !$this->appInstance->trial_ends_at ||
            !$this->appInstance->trial_ends_at->isPast() ||
            $this->appInstance->trial_complete_email_sent) {
                $this->appInstance->info('Trial complete email not sent', [
                    'app_instance_id' => $this->appInstance->id,
                    'is_trial' => $this->appInstance->is_trial,
                    'send_trial_complete_email' => $this->appInstance->storeApp->send_trial_complete_email,
                    'trial_ends_at' => $this->appInstance->trial_ends_at,
                    'trial_complete_email_sent' => $this->appInstance->trial_complete_email_sent,
                ]);
            return;
        }

        // Send email to owners      
        foreach($this->appInstance->userGroup->owners as $owner) {
            $mail = Mail::to($owner->email);
                    
            if(env('MAIL_CC_ALL', false)) {
                $mail->cc(env('MAIL_CC_ALL'));
            }

            $this->appInstance->info('Sending trial complete email to owner', [
                'owner_id' => $owner->id,
                'owner_email' => $owner->email,
            ]);
            
            $mail->queue(new AppInstanceTrialCompleteMail($this->appInstance, $owner));
        }

        // Update the sent flag
        $this->appInstance->update(['trial_complete_email_sent' => true]);

        $this->polydockJobDone();
    }
} 