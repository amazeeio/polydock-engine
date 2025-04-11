<?php

namespace App\Mail;

use App\Models\PolydockAppInstance;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AppInstanceTrialCompleteMail extends Mailable
{
    use Queueable, SerializesModels;

    public PolydockAppInstance $appInstance;

    public function __construct(PolydockAppInstance $appInstance)
    {
        $this->appInstance = $appInstance;
    }

    public function build()
    {
        $subject = $this->appInstance->storeApp->trial_complete_email_subject ?? 'Your Trial Has Ended';
        return $this->markdown('emails.app-instance.trial-complete')
                    ->subject($subject);
    }
} 