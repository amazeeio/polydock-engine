<?php

namespace App\Mail;

use App\Models\PolydockAppInstance;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AppInstanceTrialCompleteMail extends Mailable
{
    use Queueable, SerializesModels;

    public PolydockAppInstance $appInstance;

    public User $toUser;

    public function __construct(PolydockAppInstance $appInstance, User $toUser)
    {
        $this->appInstance = $appInstance;
        $this->toUser = $toUser;
    }

    public function build()
    {
        $subject = $this->appInstance->storeApp->trial_complete_email_subject ?? 'Your Trial Has Ended';
        $subject .= ' ['.$this->appInstance->name.']';

        return $this->markdown('emails.app-instance.trial-complete')
            ->subject($subject);
    }
}
