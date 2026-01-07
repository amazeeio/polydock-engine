<?php

namespace App\Mail;

use App\Mail\Traits\ResolvesThemeTemplate;
use App\Models\PolydockAppInstance;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AppInstanceReadyMail extends Mailable
{
    use Queueable, SerializesModels, ResolvesThemeTemplate;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public PolydockAppInstance $appInstance,
        public User $toUser,
        public string $markdownTemplate = 'emails.app-instance.ready'
    ) {
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $this->resolveThemeTemplate($this->appInstance->storeApp->mail_theme, $this->markdownTemplate);
        
        $subject = $this->appInstance->storeApp->email_subject_line;
        
        if (empty($subject)) {
            $subject = "Your new instance is Ready";
        }

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
        return new Content(
            markdown: $this->markdownTemplate,
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