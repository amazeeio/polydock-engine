<?php

declare(strict_types=1);

namespace App\Mail;

class AppInstanceMidtrialMail extends TrialEmailMail
{
    protected string $subjectField = 'midtrial_email_subject';

    protected string $defaultSubject = 'Halfway Through Your Trial';

    protected string $mailView = 'emails.app-instance.midtrial';
}
