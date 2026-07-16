<?php

declare(strict_types=1);

namespace App\Mail;

use App\Mail\Traits\AppliesMailTheme;
use App\Models\PolydockAppInstance;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Shared shape of the trial lifecycle emails: subclasses only declare which
 * store-app field overrides the subject, the default subject, and the view.
 */
abstract class TrialEmailMail extends Mailable
{
    use AppliesMailTheme;
    use Queueable;
    use SerializesModels;

    /** Store-app column holding the optional subject override. */
    protected string $subjectField;

    protected string $defaultSubject;

    protected string $mailView;

    public function __construct(
        public PolydockAppInstance $appInstance,
        public User $toUser,
    ) {}

    public function envelope(): Envelope
    {
        $subject = $this->appInstance->storeApp->{$this->subjectField} ?? $this->defaultSubject;
        $subject .= ' ['.$this->appInstance->name.']';

        return new Envelope(
            subject: $subject,
        );
    }

    public function content(): Content
    {
        $mjmlConfig = $this->mjmlConfig();
        $mjmlConfig['appInstance'] = $this->appInstance;

        return new Content(
            view: $this->mailView,
            with: ['config' => $mjmlConfig],
        );
    }
}
