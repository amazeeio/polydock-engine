<?php

declare(strict_types=1);

namespace App\Mail;

use App\Mail\Traits\AppliesMailTheme;
use App\Models\PolydockAppInstance;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AppInstanceReadyMail extends Mailable
{
    use AppliesMailTheme;
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
        $mjmlConfig = $this->mjmlConfig();

        return new Content(
            view: 'emails.app-instance.ready',
            with: ['config' => $mjmlConfig],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
