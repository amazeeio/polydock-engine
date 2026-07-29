<?php

declare(strict_types=1);

namespace App\Mail;

class AppInstanceTrialCompleteMail extends TrialEmailMail
{
    protected string $subjectField = 'trial_complete_email_subject';

    protected string $defaultSubject = 'Your Trial Has Ended';

    protected string $mailView = 'emails.app-instance.trial-complete';
}
