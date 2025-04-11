<?php

namespace App\Jobs\ProcessPolydockAppInstanceJobs\Trial;

use App\Mail\AppInstanceOneDayLeftMail;
use App\Models\PolydockAppInstance;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class ProcessOneDayLeftEmailJob implements ShouldQueue
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
            !$this->appInstance->storeApp->send_one_day_left_email ||
            !$this->appInstance->send_one_day_left_email_at ||
            !$this->appInstance->send_one_day_left_email_at->isPast() ||
            $this->appInstance->one_day_left_email_sent) {
            return;
        }

        // Send email
        Mail::to($this->appInstance->userGroup->owner)->send(new AppInstanceOneDayLeftMail($this->appInstance));

        // Update the sent flag
        $this->appInstance->update(['one_day_left_email_sent' => true]);
    }
} 