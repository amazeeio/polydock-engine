<?php

namespace App\Jobs\ProcessPolydockAppInstanceJobs\Trial;

use App\Jobs\ProcessPolydockAppInstanceJobs\BaseJob;
use App\Mail\TrialEmailMail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Shared shape of the trial lifecycle email jobs. Subclasses declare which
 * store-app toggle, schedule field, sent-flag, Mailable, and label to use;
 * the guard/send/mark-sent flow is identical for all of them.
 */
abstract class TrialEmailJob extends BaseJob
{
    /** Store-app boolean enabling this email. */
    protected string $enabledField;

    /** App-instance datetime that must be in the past before sending. */
    protected string $atField;

    /** App-instance boolean marking this email as sent. */
    protected string $sentFlag;

    /** @var class-string<TrialEmailMail> */
    protected string $mailClass;

    /** Sentence-case label used in log messages, e.g. 'Midtrial'. */
    protected string $label;

    /** Whether an already-expired trial skips the send (but still marks sent). */
    protected bool $skipWhenExpired = true;

    public function handle()
    {
        $this->polydockJobStart();

        $instance = $this->appInstance;
        $context = [
            'app_instance_id' => $instance->id,
            'is_trial' => $instance->is_trial,
            $this->enabledField => $instance->storeApp->{$this->enabledField},
            $this->atField => $instance->{$this->atField},
            $this->sentFlag => $instance->{$this->sentFlag},
        ];

        if (
            ! $instance->is_trial
            || ! $instance->storeApp->{$this->enabledField}
            || ! $instance->{$this->atField}
            || ! $instance->{$this->atField}->isPast()
            || $instance->{$this->sentFlag}
        ) {
            $instance->info("{$this->label} email not sent", $context);
            Log::info("{$this->label} email not sent", $context);

            return;
        }

        if ($this->skipWhenExpired && $instance->isTrialExpired()) {
            $message = 'Trial expired, skipping '.lcfirst($this->label).' email but marking as sent';
            $instance->info($message, ['app_instance_id' => $instance->id]);
            Log::info($message, $context);
        } else {
            $message = 'Sending '.lcfirst($this->label).' email to owners';
            $instance->info($message, ['app_instance_id' => $instance->id]);
            Log::info($message, ['app_instance_id' => $instance->id]);

            // With >1 owner, a mid-loop send failure would re-email earlier owners on re-dispatch.
            // Add per-owner sent-tracking if multi-owner groups ever become real.
            foreach ($instance->userGroup->owners as $owner) {
                $mail = Mail::to($owner->email);

                if (config('mail.cc_all')) {
                    $mail->cc(config('mail.cc_all'));
                }

                $ownerContext = [
                    'owner_id' => $owner->id,
                    'owner_email' => $owner->email,
                    'app_instance_id' => $instance->id,
                ];
                $message = 'Sending '.lcfirst($this->label).' email to owner';
                $instance->info($message, $ownerContext);
                Log::info($message, $ownerContext);

                $mail->send(new $this->mailClass($instance, $owner));
            }
        }

        $instance->update([$this->sentFlag => true]);

        $this->polydockJobDone();
    }
}
