<?php

namespace App\Forms;

use App\Enums\PolydockStoreAppStatusEnum;
use App\Enums\PolydockStoreStatusEnum;
use App\Rules\BannedEmail;
use Illuminate\Validation\Rule;

abstract class BaseHostedForm implements HostedFormInterface
{
    /**
     * Baseline rules shared by every hosted form: contact details and a
     * publicly-available trial app. Concrete forms merge their extra fields
     * on top via array_merge(parent::getValidationRules(), [...]).
     */
    #[\Override]
    public function getValidationRules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', new BannedEmail],
            'trial_app' => [
                'required',
                'uuid',
                Rule::exists('polydock_store_apps', 'uuid')
                    ->where('status', PolydockStoreAppStatusEnum::AVAILABLE->value)
                    ->where('available_for_trials', true)
                    ->where(function ($query) {
                        $query->whereExists(function ($subQuery) {
                            $subQuery->selectRaw(1)
                                ->from('polydock_stores')
                                ->whereColumn('polydock_stores.id', 'polydock_store_apps.polydock_store_id')
                                ->where('polydock_stores.status', PolydockStoreStatusEnum::PUBLIC->value);
                        });
                    }),
            ],
        ];
    }

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
        $domains = [
            'amazee.ai',
            'www.amazee.ai',
        ];

        if (! app()->isProduction()) {
            $domains[] = 'localhost';
        }

        return $domains;
    }

    #[\Override]
    public function getRecaptchaEnabled(): bool
    {
        return (bool) config('services.recaptcha.enabled', true);
    }

    #[\Override]
    public function getAllowedEmbedOrigins(): array
    {
        $origins = [];
        foreach ($this->getAllowedEmbedDomains() as $domain) {
            if ($domain === 'localhost') {
                $origins[] = 'http://localhost';
                $origins[] = 'http://localhost:*';
            } else {
                $origins[] = "https://{$domain}";
            }
        }

        return $origins;
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
            'trial_app' => $validatedData['trial_app'] ?? null,
        ];
    }
}
