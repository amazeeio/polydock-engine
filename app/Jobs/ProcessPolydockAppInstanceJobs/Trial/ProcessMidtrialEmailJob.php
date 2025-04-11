<?php

namespace App\Jobs\ProcessPolydockAppInstanceJobs\Trial;

use App\Mail\AppInstanceMidtrialMail;
use App\Models\PolydockAppInstance;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class ProcessMidtrialEmailJob implements ShouldQueue
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
            !$this->appInstance->storeApp->send_midtrial_email ||
            !$this->appInstance->send_midtrial_email_at ||
            !$this->appInstance->send_midtrial_email_at->isPast() ||
            $this->appInstance->midtrial_email_sent) {
                $this->appInstance->info('Midtrial email not sent', [
                    'app_instance_id' => $this->appInstance->id,
                    'is_trial' => $this->appInstance->is_trial,
                    'send_midtrial_email' => $this->appInstance->storeApp->send_midtrial_email,
                    'send_midtrial_email_at' => $this->appInstance->send_midtrial_email_at,
                    'midtrial_email_sent' => $this->appInstance->midtrial_email_sent,
                ]);
            return;
        }

        // Send email
        Mail::to($this->appInstance->userGroup->owner)->send(new AppInstanceMidtrialMail($this->appInstance));

        // Update the sent flag
        $this->appInstance->update(['midtrial_email_sent' => true]);
    }
} 