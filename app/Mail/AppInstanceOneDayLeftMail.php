<?php

declare(strict_types=1);

namespace App\Mail;

class AppInstanceOneDayLeftMail extends TrialEmailMail
{
    protected string $subjectField = 'one_day_left_email_subject';

    protected string $defaultSubject = 'One Day Left in Your Trial';

    protected string $mailView = 'emails.app-instance.one-day-left';
}
