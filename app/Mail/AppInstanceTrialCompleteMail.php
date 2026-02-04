<?php

namespace App\Mail;

use App\Models\PolydockAppInstance;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Facades\Config;
use Illuminate\Mail\Mailables\Content;

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

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $subject = $this->appInstance->storeApp->trial_complete_email_subject ?? 'Your Trial Has Ended';
        $subject .= " [" . $this->appInstance->name . "]";

        return new Envelope(
            subject: $subject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $mjmlConfig = Config::get('mail.mjml-config');
        // $mjmlConfig['theme'] = $mjmlConfig['themes']['dark'];
        $mjmlConfig['appInstance'] = $this->appInstance;
        
        return new Content(
            view: 'emails.app-instance.trial-complete',
            with: ['config'=>$mjmlConfig],
        );
    }
} 