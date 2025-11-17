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

    public function __construct(
        public PolydockAppInstance $appInstance,
        public User $toUser,
        public string $markdownTemplate = 'emails.app-instance.trial-complete'
    ) {}

    public function build()
    {
        $subject = $this->appInstance->storeApp->trial_complete_email_subject ?? 'Your Trial Has Ended';
        $subject .= " [" . $this->appInstance->name . "]";

        return $this->markdown($this->markdownTemplate)
                    ->subject($subject);
    }
} 