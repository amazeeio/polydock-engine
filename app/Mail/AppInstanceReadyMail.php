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

class AppInstanceReadyMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public PolydockAppInstance $appInstance,
        public User $toUser,
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $subject = $this->appInstance->storeApp->email_subject_line;

        if (empty($subject)) {
            $subject = 'Your new instance is Ready';
        }

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
        // dd(Config::get('mail.mjml-config'));
        $mjmlConfig = Config::get('mail.mjml-config');

        // $mjmlConfig['theme'] = $mjmlConfig['themes']['dark'];

        return new Content(
            // markdown: 'emails.app-instance.ready',
            view: 'emails.app-instance.ready',
            with: ['config' => $mjmlConfig],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
