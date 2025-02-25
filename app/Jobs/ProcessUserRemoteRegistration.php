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

class ProcessUserRemoteRegistration implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

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
        $registerType = $this->registration->getRequestValue('register_type') ?? 'UNKNOWN';
        Log::info('Processing user remote registration', ['registration' => $this->registration->toArray(), 'register_type' => $registerType]);

        match($registerType) {
            'TEST_FAIL' => $this->handleTestFail(),
            'REQUEST_TRIAL' => $this->handleRequestTrial(),
            default => $this->handleUnknownType(),
        };

        $this->registration->save();
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
        $this->registration->status = UserRemoteRegistrationStatusEnum::SUCCESS;

        if ($this->registration->id % 2 === 0) {
            $uniqueId = Str::random(10);
            $this->registration->setResultValue('message', 'Trial created.');
            $this->registration->setResultValue('trial_app_url', "https://www.example.com/{$uniqueId}");
        } else {
            $this->registration->setResultValue('message', 'You have been registered for a trial allocation. We will email you');
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