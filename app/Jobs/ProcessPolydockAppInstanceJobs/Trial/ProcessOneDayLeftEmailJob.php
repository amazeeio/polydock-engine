<?php

namespace App\Jobs\ProcessPolydockAppInstanceJobs\Trial;

use App\Mail\AppInstanceOneDayLeftMail;

class ProcessOneDayLeftEmailJob extends TrialEmailJob
{
    protected string $enabledField = 'send_one_day_left_email';

    protected string $atField = 'send_one_day_left_email_at';

    protected string $sentFlag = 'one_day_left_email_sent';

    protected string $mailClass = AppInstanceOneDayLeftMail::class;

    protected string $label = 'One day left';
}
