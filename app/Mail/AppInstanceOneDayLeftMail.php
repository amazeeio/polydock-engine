<?php

namespace App\Mail;

use App\Models\PolydockAppInstance;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AppInstanceOneDayLeftMail extends Mailable
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
        $subject = $this->appInstance->storeApp->one_day_left_email_subject ?? 'One Day Left in Your Trial';
        $subject .= ' ['.$this->appInstance->name.']';

        return $this->markdown('emails.app-instance.one-day-left')
            ->subject($subject);
    }
}
