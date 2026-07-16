<?php

namespace App\Jobs\ProcessPolydockAppInstanceJobs\Trial;

use App\Mail\AppInstanceTrialCompleteMail;

class ProcessTrialCompleteEmailJob extends TrialEmailJob
{
    protected string $enabledField = 'send_trial_complete_email';

    protected string $atField = 'trial_ends_at';

    protected string $sentFlag = 'trial_complete_email_sent';

    protected string $mailClass = AppInstanceTrialCompleteMail::class;

    protected string $label = 'Trial complete';

    // The trial-complete email is sent after expiry by definition.
    protected bool $skipWhenExpired = false;
}
