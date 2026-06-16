<?php

namespace App\Forms;

abstract class BaseHostedForm implements HostedFormInterface
{
    #[\Override]
    public function getSeoTitle(): string
    {
        return $this->getTitle().' | Polydock';
    }

    #[\Override]
    public function getSeoDescription(): string
    {
        return 'Provision and try a trial environment instantly with Polydock.';
    }

    #[\Override]
    public function getAllowedEmbedDomains(): array
    {
        return [
            'amazee.ai',
            'www.amazee.ai',
            'localhost',
        ];
    }

    #[\Override]
    public function getRecaptchaEnabled(): bool
    {
        return true;
    }

    /**
     * Map form submission fields to the schema required by UserRemoteRegistration
     */
    #[\Override]
    public function transformPayload(array $validatedData): array
    {
        return [
            'email' => $validatedData['email'],
            'first_name' => $validatedData['first_name'] ?? '',
            'last_name' => $validatedData['last_name'] ?? '',
            'organization' => $validatedData['organization'] ?? '',
            'job_title' => $validatedData['job_title'] ?? '',
            'register_type' => 'REQUEST_TRIAL',
            'aup_and_privacy_acceptance' => 1,
            'opt_in_to_product_updates' => 1,
            'trial_app' => $validatedData['trial_app'],
        ];
    }
}
