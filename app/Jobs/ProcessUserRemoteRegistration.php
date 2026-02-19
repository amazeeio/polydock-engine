<?php

namespace App\Jobs;

use App\Enums\PolydockStoreAppStatusEnum;
use App\Enums\UserGroupRoleEnum;
use App\Enums\UserRemoteRegistrationStatusEnum;
use App\Enums\UserRemoteRegistrationType;
use App\Models\PolydockAppInstance;
use App\Models\PolydockStoreApp;
use App\Models\User;
use App\Models\UserGroup;
use App\Models\UserRemoteRegistration;
use App\Services\PolydockAppClassDiscovery;
use FreedomtechHosting\PolydockApp\Attributes\PolydockAppInstanceFields;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ProcessUserRemoteRegistration implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Required fields in the request data
     */
    private const array REQUIRED_FIELDS = [
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
        private UserRemoteRegistration $registration,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('Starting to process user remote registration', [
            'registration_id' => $this->registration->id,
            'uuid' => $this->registration->uuid,
        ]);

        if (! $this->validateRequestData()) {
            $this->registration->status = UserRemoteRegistrationStatusEnum::FAILED;
            $this->registration->setResultValue('message', 'Malformed registration request');
            $this->registration->save();

            Log::warning('Malformed registration request', [
                'registration_id' => $this->registration->id,
                'uuid' => $this->registration->uuid,
                'request_data' => $this->registration->request_data,
            ]);

            return;
        }

        // Set the type from request_data
        $this->registration->type = UserRemoteRegistrationType::from(
            $this->registration->getRequestValue('register_type'),
        );
        $this->registration->save();

        match ($this->registration->type) {
            UserRemoteRegistrationType::TEST_FAIL => $this->handleTestFail(),
            UserRemoteRegistrationType::REQUEST_TRIAL => $this->handleRequestTrial(),
            UserRemoteRegistrationType::REQUEST_TRIAL_UNLISTED_REGION => $this->handleRequestUnlistedRegion(),
            default => $this->handleUnknownType(),
        };

        $this->registration->save();
    }

    /**
     * Validate that all required fields are present in the request data
     */
    private function validateRequestData(): bool
    {
        Log::info('Validating request data', ['registration' => $this->registration->toArray()]);

        // First check if all required fields exist
        foreach (self::REQUIRED_FIELDS as $field) {
            if (is_null($this->registration->getRequestValue($field))) {
                // Allow missing trial_app field for unlisted region requests
                if (
                    $field === 'trial_app'
                    && $this->registration->getRequestValue('register_type') === 'REQUEST_TRIAL_UNLISTED_REGION'
                ) {
                    continue;
                }

                Log::warning("Missing required field: {$field}", [
                    'registration_id' => $this->registration->id,
                    'uuid' => $this->registration->uuid,
                ]);

                return false;
            }
        }

        // Check if AUP and privacy acceptance is valid
        if ($this->registration->getRequestValue('aup_and_privacy_acceptance') !== 1) {
            Log::warning('AUP and privacy acceptance must be accepted', [
                'registration_id' => $this->registration->id,
                'uuid' => $this->registration->uuid,
                'aup_and_privacy_acceptance' => $this->registration->getRequestValue('aup_and_privacy_acceptance'),
            ]);

            $this->registration->status = UserRemoteRegistrationStatusEnum::FAILED;
            $this->registration->setResultValue(
                'message_detail',
                'You must accept the AUP and Privacy Policy to proceed',
            );
            $this->registration->save();

            return false;
        }

        $registerType = $this->registration->getRequestValue('register_type');
        $trialAppId = $this->registration->getRequestValue('trial_app');

        if ($registerType != 'REQUEST_TRIAL_UNLISTED_REGION') { // Validate trial app exists and is available for trials
            Log::info('Validating trial app', ['registration' => $this->registration->toArray()]);

            try {
                $trialApp = PolydockStoreApp::where('uuid', $trialAppId)->firstOrFail();

                // Check both conditions together to avoid leaking information
                if (! $trialApp->available_for_trials || $trialApp->status !== PolydockStoreAppStatusEnum::AVAILABLE) {
                    // Still log the specific reason for monitoring purposes
                    Log::warning('Trial app validation failed', [
                        'registration_id' => $this->registration->id,
                        'uuid' => $this->registration->uuid,
                        'trial_app_uuid' => $trialApp->uuid,
                        'available_for_trials' => $trialApp->available_for_trials,
                        'status' => $trialApp->status->value,
                    ]);

                    $this->registration->status = UserRemoteRegistrationStatusEnum::FAILED;
                    $this->registration->setResultValue('message_detail', 'The requested trial is not available');
                    $this->registration->save();

                    return false;
                }
            } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
                // Log the real reason but return a vague message
                Log::warning('Trial app not found', [
                    'registration_id' => $this->registration->id,
                    'uuid' => $this->registration->uuid,
                    'trial_app_uuid' => $this->registration->getRequestValue('trial_app'),
                ]);

                $this->registration->status = UserRemoteRegistrationStatusEnum::FAILED;
                $this->registration->setResultValue('message_detail', 'The requested trial is not available');
                $this->registration->save();

                return false;
            }
        }

        Log::info('Request data validated successfully', ['registration' => $this->registration->toArray()]);

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

            if (! $user) {
                $user = User::create([
                    'first_name' => $this->registration->getRequestValue('first_name'),
                    'last_name' => $this->registration->getRequestValue('last_name'),
                    'email' => $this->registration->getRequestValue('email'),
                    'password' => Str::random(32),
                    'email_verified_at' => now(),
                ]);

                Log::info('Created new user for trial', [
                    'user_id' => $user->id,
                    'registration_id' => $this->registration->id,
                ]);
            }

            // Check if user has any groups
            if ($user->groups()->count() === 0) {
                $groupName = $user->name.' Trials';

                $group = UserGroup::create([
                    'name' => $groupName,
                ]);

                $user->groups()->attach($group, [
                    'role' => UserGroupRoleEnum::OWNER->value,
                ]);

                Log::info('Created new group for user', [
                    'user_id' => $user->id,
                    'group_id' => $group->id,
                    'registration_id' => $this->registration->id,
                ]);
            }

            // Update registration with user info
            $this->registration->user_id = $user->id;
            $this->registration->user_group_id = $user->groups()->first()->id;
            $this->registration->status = UserRemoteRegistrationStatusEnum::SUCCESS;
            $this->registration->save();

            $email = $user->email;
            $domain = null;
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $emailParts = explode('@', (string) $email);
                if (count($emailParts) === 2) {
                    $domain = $emailParts[1];
                }
            }
            $this->registration->setResultValue('user_email_domain', $domain);

            $trialApp = PolydockStoreApp::where(
                'uuid',
                $this->registration->getRequestValue('trial_app'),
            )->firstOrFail();
            $this->registration->polydock_store_app_id = $trialApp->id;

            if ($this->registration->registerSimulateRoundRobin) {
                Log::info('Simulating round robin registration', ['registration' => $this->registration->toArray()]);
                // Set success message and URL if even ID
                if (($this->registration->id % 2) === 0) {
                    $uniqueId = Str::random(10);
                    $this->registration->setResultValue('result_type', 'trial_created');
                    $this->registration->setResultValue('message', 'Trial created.');
                    $this->registration->setResultValue('trial_app_url', "https://www.example.com/{$uniqueId}");
                } elseif (($this->registration->id % 3) === 0) {
                    throw new \Exception('An error occurred while processing the registration.');
                } else {
                    $this->registration->setResultValue('result_type', 'trial_registered');
                    $this->registration->setResultValue('message', 'You have been registered for a trial allocation.');
                }
            } elseif ($this->registration->registerOnlyCaptures) {
                Log::info('Only capturing registration', ['registration' => $this->registration->toArray()]);
                $this->registration->setResultValue('result_type', 'trial_registered');
                $this->registration->setResultValue('message', 'You have been registered for a trial allocation.');
            } elseif ($this->registration->registerSimulateError) {
                Log::info('Simulating error registration', ['registration' => $this->registration->toArray()]);
                throw new \Exception('An error occurred while processing the registration.');
            } else {
                Log::info('Starting actual trial creation', ['registration' => $this->registration->toArray()]);
                $this->registration->status = UserRemoteRegistrationStatusEnum::PROCESSING;

                $this->registration->setResultValue('result_type', 'processing');
                $this->registration->setResultValue('message', 'Your trial is being created...');

                $allocatedInstance = $this->registration->userGroup->getNewAppInstanceForThisApp($trialApp);
                $allocatedInstance->is_trial = true;
                $allocatedInstance->calculateAndSetTrialDates();
                $allocatedInstance->save();

                $this->registration->polydock_app_instance_id = $allocatedInstance->id;
                $this->registration->save();

                // Add user information to the app instance data
                $allocatedInstance->storeKeyValue(
                    'user-first-name',
                    $this->registration->getRequestValue('first_name'),
                );
                $allocatedInstance->storeKeyValue('user-last-name', $this->registration->getRequestValue('last_name'));
                $allocatedInstance->storeKeyValue('user-email', $this->registration->getRequestValue('email'));
                // if they add a company name, we store it too
                $companyName = $this->registration->getRequestValue('company_name');
                if ($companyName) {
                    $allocatedInstance->storeKeyValue('company-name', $companyName);
                }

                $allocatedInstance->save();

                // Store instance config fields as PolydockVariables
                $this->storeInstanceConfigFields($allocatedInstance, $trialApp);
            }
        } catch (\Exception $e) {
            Log::error('Failed to process trial registration', [
                'registration_id' => $this->registration->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->registration->status = UserRemoteRegistrationStatusEnum::FAILED;
            $this->registration->setResultValue('message', 'Failed to process registration.');
            $this->registration->setResultValue('message_detail', 'An unexpected error occurred.');
            $this->registration->setResultValue('result_type', 'registration_failed');
        }
    }

    /**
     * Handle unlisted region trial request registration
     */
    private function handleRequestUnlistedRegion(): void
    {
        // TODO: Implement unlisted region trial request handling
        Log::info('Handling unlisted region trial request registration', ['registration' => $this->registration->toArray()]);
        $this->registration->status = UserRemoteRegistrationStatusEnum::SUCCESS;
        $this->registration->setResultValue('result_type', 'trial_registered');
        $this->registration->setResultValue('message', 'You have been registered for a trial allocation.');
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

    /**
     * Store instance config fields as PolydockVariables on the app instance.
     *
     * Extracts fields prefixed with 'instance_config_' from the registration request data
     * and stores them as PolydockVariables, respecting encryption settings.
     *
     * @param  \App\Models\PolydockAppInstance  $appInstance  The app instance to store variables on
     * @param  PolydockStoreApp  $storeApp  The store app to get field schema from
     */
    private function storeInstanceConfigFields(PolydockAppInstance $appInstance, PolydockStoreApp $storeApp): void
    {
        $instanceConfigPrefix = PolydockAppInstanceFields::FIELD_PREFIX;
        $requestData = $this->registration->request_data ?? [];

        // Get field encryption map from the app class schema
        $discovery = app(PolydockAppClassDiscovery::class);
        $schema = $discovery->getAppInstanceFormSchema($storeApp->polydock_app_class ?? '');
        $encryptionMap = $discovery->getFieldEncryptionMap($schema);

        $storedFields = [];

        foreach ($requestData as $key => $value) {
            if (str_starts_with((string) $key, $instanceConfigPrefix) && $value !== null && $value !== '') {
                $encrypted = $encryptionMap[$key] ?? false;
                $appInstance->setPolydockVariableValue($key, (string) $value, $encrypted);
                $storedFields[] = $key;
            }
        }

        if (! empty($storedFields)) {
            Log::info('Stored instance config fields as PolydockVariables', [
                'registration_id' => $this->registration->id,
                'app_instance_id' => $appInstance->id,
                'fields' => $storedFields,
            ]);
        }
    }
}
