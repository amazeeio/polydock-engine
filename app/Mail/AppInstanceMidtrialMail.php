<?php

namespace App\Mail;

use App\Models\PolydockAppInstance;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AppInstanceMidtrialMail extends Mailable
{
    use Queueable, SerializesModels;

    public PolydockAppInstance $appInstance;

    public function __construct(PolydockAppInstance $appInstance)
    {
        $this->appInstance = $appInstance;
    }

    public function build()
    {
        $subject = $this->appInstance->storeApp->midtrial_email_subject ?? 'Halfway Through Your Trial';
        return $this->markdown('emails.app-instance.midtrial')
                    ->subject($subject);
    }
} 