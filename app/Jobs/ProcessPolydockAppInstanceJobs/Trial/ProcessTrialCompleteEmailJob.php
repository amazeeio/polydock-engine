<?php

namespace App\Jobs\ProcessPolydockAppInstanceJobs\Trial;

use App\Mail\AppInstanceTrialCompleteMail;
use App\Models\PolydockAppInstance;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class ProcessTrialCompleteEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected PolydockAppInstance $appInstance;

    public function __construct(PolydockAppInstance $appInstance)
    {
        $this->appInstance = $appInstance;
    }

    public function handle()
    {
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

        // Send email
        Mail::to($this->appInstance->userGroup->owner)->send(new AppInstanceTrialCompleteMail($this->appInstance));

        // Update the sent flag
        $this->appInstance->update(['trial_complete_email_sent' => true]);
    }
} 