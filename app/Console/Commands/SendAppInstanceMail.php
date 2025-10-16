<?php

namespace App\Console\Commands;

use App\Mail\AppInstanceMidtrialMail;
use App\Mail\AppInstanceOneDayLeftMail;
use App\Mail\AppInstanceReadyMail;
use App\Mail\AppInstanceTrialCompleteMail;
use App\Models\PolydockAppInstance;
use App\Models\User;
use App\Models\UserRemoteRegistration;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendAppInstanceMail extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'polydock:send-instance-mail
                          {registration : The user remote registration UUID}
                          {mail-type : The type of mail to send (ready, midtrial, one-day-left, trial-complete)}
                          {email : Email address to send to}
                          {--force : Skip confirmation prompt}
                          {--dry-run : Show what would be sent without actually sending}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send a specific mailable to a specified email address based on a user remote registration UUID';

    private array $availableMailTypes = [
        'ready' => AppInstanceReadyMail::class,
        'midtrial' => AppInstanceMidtrialMail::class,
        'one-day-left' => AppInstanceOneDayLeftMail::class,
        'trial-complete' => AppInstanceTrialCompleteMail::class,
    ];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $registrationUuid = $this->argument('registration');
        $mailType = $this->argument('mail-type');
        $specificEmail = $this->argument('email');
        $force = $this->option('force');
        $dryRun = $this->option('dry-run');

        // Validate mail type
        if (!array_key_exists($mailType, $this->availableMailTypes)) {
            $this->error('Invalid mail type. Available types: ' . implode(', ', array_keys($this->availableMailTypes)));
            return 1;
        }

        // Find the user remote registration
        $registration = $this->findUserRemoteRegistration($registrationUuid);
        if (!$registration) {
            $this->error("User remote registration not found: {$registrationUuid}");
            return 1;
        }

        // Validate that the registration has an associated app instance
        if (!$registration->appInstance) {
            $this->error("No app instance associated with registration: {$registrationUuid}");
            return 1;
        }

        $instance = $registration->appInstance;

        $this->info("Found registration: {$registration->uuid}");
        $this->info("User Email: {$registration->email}");
        $this->info("App Instance: {$instance->name} (ID: {$instance->id})");
        $this->info("Store App: {$instance->storeApp->name}");
        $this->info("User Group: {$instance->userGroup->name}");
        $this->newLine();

        // Validate email address
        if (!filter_var($specificEmail, FILTER_VALIDATE_EMAIL)) {
            $this->error("Invalid email address: {$specificEmail}");
            return 1;
        }

        // Create recipient
        $recipients = $this->getRecipients($registration, $specificEmail);

        $mailableClass = $this->availableMailTypes[$mailType];
        $this->info("Mail Type: {$mailType} ({$mailableClass})");
        $this->info("Recipients: " . implode(', ', array_column($recipients, 'email')));
        $this->newLine();

        if ($dryRun) {
            $this->info('DRY RUN: Email would be sent to the above recipients.');
            return 0;
        }

        // Confirm sending unless force flag is used
        if (!$force) {
            $confirmed = $this->confirm(
                "Send {$mailType} email to " . count($recipients) . " recipient(s)?",
                false
            );

            if (!$confirmed) {
                $this->info('Operation cancelled.');
                return 0;
            }
        }

        // Send the emails
        $successCount = 0;
        $errorCount = 0;

        foreach ($recipients as $recipient) {
            try {
                $mail = Mail::to($recipient['email']);
                // dd($instance);
                // Create the mailable instance
                $mailable = new $mailableClass($instance, $recipient['user']);
                
                // Send immediately (not queued) for manual commands
                $mail->send($mailable);

                $this->info("✓ Email sent to {$recipient['name']} ({$recipient['email']})");
                $successCount++;
            } catch (\Exception $e) {
                $this->error("✗ Failed to send to {$recipient['email']}: {$e->getMessage()}");
                $errorCount++;
            }
        }

        $this->newLine();
        $this->info("Email sending completed:");
        $this->info("- Successfully sent: {$successCount}");
        if ($errorCount > 0) {
            $this->warn("- Failed to send: {$errorCount}");
        }

        return $errorCount > 0 ? 1 : 0;
    }

    /**
     * Find user remote registration by UUID
     */
    private function findUserRemoteRegistration(string $uuid): ?UserRemoteRegistration
    {
        return UserRemoteRegistration::with(['appInstance', 'storeApp', 'userGroup', 'user'])
            ->where('uuid', $uuid)
            ->first();
    }

    /**
     * Get recipients for the email
     */
    private function getRecipients(UserRemoteRegistration $registration, string $email): array
    {
        // Try to use real user data from the registration if available and matches the email
        if ($registration->user && $registration->user->email === $email) {
            $user = $registration->user;
        } else {
            // Create a user object with registration data or defaults
            $firstName = $registration->getRequestValue('first_name') ?? 'User';
            $lastName = $registration->getRequestValue('last_name') ?? 'User';
            
            $user = new User([
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $email
            ]);
        }

        return [
            [
                'user' => $user,
                'email' => $email,
                'name' => $user->name ?? trim("{$user->first_name} {$user->last_name}")
            ]
        ];
    }
}