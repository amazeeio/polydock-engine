<?php

namespace App\Mail;

use App\Mail\Traits\ResolvesThemeTemplate;
use App\Models\PolydockAppInstance;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AppInstanceOneDayLeftMail extends Mailable
{
    use Queueable, SerializesModels, ResolvesThemeTemplate;

    public function __construct(
        public PolydockAppInstance $appInstance,
        public User $toUser,
        public string $markdownTemplate = 'emails.app-instance.one-day-left'
    ) {
    }

    public function build()
    {
        $this->resolveThemeTemplate($this->appInstance->storeApp->mail_theme, $this->markdownTemplate);
        
        $subject = $this->appInstance->storeApp->one_day_left_email_subject ?? 'One Day Left in Your Trial';
        $subject .= " [" . $this->appInstance->name . "]";
        
        return $this->markdown($this->markdownTemplate)
                    ->subject($subject);
    }
} 