<?php

namespace App\Jobs;

use App\Models\UserRemoteRegistration;
use App\Enums\UserRemoteRegistrationStatusEnum;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use App\Models\UserGroup;
use App\Enums\UserGroupRoleEnum;
use App\Models\PolydockStoreApp;
use App\Enums\PolydockStoreAppStatusEnum;

class ProcessUserRemoteRegistration implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Required fields in the request data
     *
     * @var array
     */
    private const REQUIRED_FIELDS = [
        'register_type',
        'email',
        'first_name',
        'last_name',
        'trial_app',
        'aup_and_privacy_acceptance',
        'opt_in_to_product_updates',
    ];

    /**
     * Create a new job instance.
     */
    public function __construct(
        private UserRemoteRegistration $registration
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('Starting to process user remote registration', [
            'registration_id' => $this->registration->id,
            'uuid' => $this->registration->uuid
        ]);

        if (!$this->validateRequestData()) {
            $this->registration->status = UserRemoteRegistrationStatusEnum::FAILED;
            $this->registration->setResultValue('message', 'Malformed registration request');
            $this->registration->save();
            
            Log::warning('Malformed registration request', [
                'registration_id' => $this->registration->id,
                'uuid' => $this->registration->uuid,
                'request_data' => $this->registration->request_data
            ]);
            
            return;
        }

        $registerType = $this->registration->getRequestValue('register_type');

        match($registerType) {
            'TEST_FAIL' => $this->handleTestFail(),
            'REQUEST_TRIAL' => $this->handleRequestTrial(),
            default => $this->handleUnknownType(),
        };

        $this->registration->save();
    }

    /**
     * Validate that all required fields are present in the request data
     */
    private function validateRequestData(): bool
    {
        // First check if all required fields exist
        foreach (self::REQUIRED_FIELDS as $field) {
            if (is_null($this->registration->getRequestValue($field))) {
                Log::warning("Missing required field: {$field}", [
                    'registration_id' => $this->registration->id,
                    'uuid' => $this->registration->uuid
                ]);
                return false;
            }
        }

        // Check if AUP and privacy acceptance is valid
        if ($this->registration->getRequestValue('aup_and_privacy_acceptance') !== 1) {
            Log::warning("AUP and privacy acceptance must be accepted", [
                'registration_id' => $this->registration->id,
                'uuid' => $this->registration->uuid,
                'aup_and_privacy_acceptance' => $this->registration->getRequestValue('aup_and_privacy_acceptance')
            ]);
            
            $this->registration->status = UserRemoteRegistrationStatusEnum::FAILED;
            $this->registration->setResultValue('message_detail', 'You must accept the AUP and Privacy Policy to proceed');
            $this->registration->save();
            
            return false;
        }

        // Validate trial app exists and is available for trials
        try {
            $trialApp = PolydockStoreApp::where('uuid', $this->registration->getRequestValue('trial_app'))->firstOrFail();
            
            // Check both conditions together to avoid leaking information
            if (!$trialApp->available_for_trials || $trialApp->status !== PolydockStoreAppStatusEnum::AVAILABLE) {
                // Still log the specific reason for monitoring purposes
                Log::warning("Trial app validation failed", [
                    'registration_id' => $this->registration->id,
                    'uuid' => $this->registration->uuid,
                    'trial_app_uuid' => $trialApp->uuid,
                    'available_for_trials' => $trialApp->available_for_trials,
                    'status' => $trialApp->status->value
                ]);
                
                $this->registration->status = UserRemoteRegistrationStatusEnum::FAILED;
                $this->registration->setResultValue('message_detail', 'The requested trial is not available');
                $this->registration->save();
                
                return false;
            }

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            // Log the real reason but return a vague message
            Log::warning("Trial app not found", [
                'registration_id' => $this->registration->id,
                'uuid' => $this->registration->uuid,
                'trial_app_uuid' => $this->registration->getRequestValue('trial_app')
            ]);
            
            $this->registration->status = UserRemoteRegistrationStatusEnum::FAILED;
            $this->registration->setResultValue('message_detail', 'The requested trial is not available');
            $this->registration->save();
            
            return false;
        }

        return true;
    }

    /**
     * Handle test failure registration
     */
    private function handleTestFail(): void
    {
        Log::info('Handling test failure registration', ['registration' => $this->registration->toArray()]);
        $this->registration->status = UserRemoteRegistrationStatusEnum::FAILED;
        $this->registration->setResultValue('message', 'Registration failed at your request');
    }

    /**
     * Handle trial request registration
     */
    private function handleRequestTrial(): void
    {
        Log::info('Handling request trial registration', ['registration' => $this->registration->toArray()]);

        try {
            // Find or create the user
            $user = User::where('email', $this->registration->getRequestValue('email'))->first();
            
            if (!$user) {
                $user = User::create([
                    'first_name' => $this->registration->getRequestValue('first_name'),
                    'last_name' => $this->registration->getRequestValue('last_name'),
                    'email' => $this->registration->getRequestValue('email'),
                    'password' => Str::random(32),
                    'email_verified_at' => now(),
                ]);

                Log::info('Created new user for trial', [
                    'user_id' => $user->id,
                    'registration_id' => $this->registration->id
                ]);
            }

            // Check if user has any groups
            if ($user->groups()->count() === 0) {
                $groupName = $user->name . ' Trials';
                
                $group = UserGroup::create([
                    'name' => $groupName,
                ]);

                $user->groups()->attach($group, [
                    'role' => UserGroupRoleEnum::OWNER->value
                ]);

                Log::info('Created new group for user', [
                    'user_id' => $user->id,
                    'group_id' => $group->id,
                    'registration_id' => $this->registration->id
                ]);
            }

            // Update registration with user info
            $this->registration->user_id = $user->id;
            $this->registration->user_group_id = $user->groups()->first()->id;
            $this->registration->status = UserRemoteRegistrationStatusEnum::SUCCESS;

            $email = $user->email;
            $domain = null;
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $emailParts = explode('@', $email);
                if (count($emailParts) === 2) {
                    $domain = $emailParts[1];
                }
            }
            $this->registration->setResultValue('user_email_domain', $domain);

            $trialApp = PolydockStoreApp::where('uuid', $this->registration->getRequestValue('trial_app'))->firstOrFail();
            $this->registration->polydock_store_app_id = $trialApp->id;

            if($this->registration->registerSimulateRoundRobin) {
                Log::info('Simulating round robin registration', ['registration' => $this->registration->toArray()]);
                // Set success message and URL if even ID
                if ($this->registration->id % 2 === 0) {
                    $uniqueId = Str::random(10);
                    $this->registration->setResultValue('result_type', 'trial_created');
                    $this->registration->setResultValue('message', 'Trial created.');
                    $this->registration->setResultValue('trial_app_url', "https://www.example.com/{$uniqueId}");
                } else if ($this->registration->id % 3 === 0) {
                    throw new \Exception('An error occurred while processing the registration.');        
                } else {
                    $this->registration->setResultValue('result_type', 'trial_registered');
                    $this->registration->setResultValue('message', 'You have been registered for a trial allocation.');
                }
            } else if($this->registration->registerOnlyCaptures) {
                Log::info('Only capturing registration', ['registration' => $this->registration->toArray()]);
                $this->registration->setResultValue('result_type', 'trial_registered');
                $this->registration->setResultValue('message', 'You have been registered for a trial allocation.');
            } else if($this->registration->registerSimulateError) {
                Log::info('Simulating error registration', ['registration' => $this->registration->toArray()]);
                throw new \Exception('An error occurred while processing the registration.');
            } else {
                Log::info('Simulating legitimate trial creation', ['registration' => $this->registration->toArray()]);
                
                // TODO: Implement actual trial creation
                $this->registration->setResultValue('result_type', 'trial_registered');
                $this->registration->setResultValue('message', 'You have been registered for a trial allocation.');
            }
        } catch (\Exception $e) {
            Log::error('Failed to process trial registration', [
                'registration_id' => $this->registration->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $this->registration->status = UserRemoteRegistrationStatusEnum::FAILED;
            $this->registration->setResultValue('message', 'Failed to process registration.');
            $this->registration->setResultValue('message_detail', 'An unexpected error occurred.');
            $this->registration->setResultValue('result_type', 'registration_failed');
        }
    }

    /**
     * Handle unknown registration type
     */
    private function handleUnknownType(): void
    {
        Log::info('Handling unknown registration type', ['registration' => $this->registration->toArray()]);
        $this->registration->status = UserRemoteRegistrationStatusEnum::FAILED;
        $this->registration->setResultValue('message', 'Unknown registration type');
    }
} 