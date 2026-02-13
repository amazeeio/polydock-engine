<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\PolydockAppInstance;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Config;

class AppInstanceOneDayLeftMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public PolydockAppInstance $appInstance,
        public User $toUser,
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $subject = $this->appInstance->storeApp->one_day_left_email_subject ?? 'One Day Left in Your Trial';
        $subject .= ' ['.$this->appInstance->name.']';

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
        $mjmlConfig['appInstance'] = $this->appInstance;

        return new Content(
            view: 'emails.app-instance.one-day-left',
            with: ['config' => $mjmlConfig],
        );
    }
}
