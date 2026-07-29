<?php

namespace App\Jobs\ProcessPolydockAppInstanceJobs\Trial;

use App\Mail\AppInstanceMidtrialMail;

class ProcessMidtrialEmailJob extends TrialEmailJob
{
    protected string $enabledField = 'send_midtrial_email';

    protected string $atField = 'send_midtrial_email_at';

    protected string $sentFlag = 'midtrial_email_sent';

    protected string $mailClass = AppInstanceMidtrialMail::class;

    protected string $label = 'Midtrial';
}
