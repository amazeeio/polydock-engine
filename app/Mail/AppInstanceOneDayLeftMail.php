<?php

namespace App\Mail;

use App\Models\PolydockAppInstance;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AppInstanceOneDayLeftMail extends Mailable
{
    use Queueable, SerializesModels;

    public PolydockAppInstance $appInstance;

    public function __construct(PolydockAppInstance $appInstance)
    {
        $this->appInstance = $appInstance;
    }

    public function build()
    {
        $subject = $this->appInstance->storeApp->one_day_left_email_subject ?? 'One Day Left in Your Trial';
        return $this->markdown('emails.app-instance.one-day-left')
                    ->subject($subject);
    }
} 