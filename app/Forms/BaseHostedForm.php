<?php

namespace App\Forms;

use App\Enums\PolydockStoreAppStatusEnum;
use App\Enums\PolydockStoreStatusEnum;
use App\Models\PolydockHostedForm;
use App\Rules\BannedEmail;
use Illuminate\Validation\Rule;

abstract class BaseHostedForm implements HostedFormInterface
{
    public function __construct(protected PolydockHostedForm $hostedForm) {}

    #[\Override]
    public function getHostedForm(): PolydockHostedForm
    {
        return $this->hostedForm;
    }

    #[\Override]
    public function getSlug(): string
    {
        return $this->hostedForm->slug;
    }

    #[\Override]
    public function getTitle(): string
    {
        return $this->hostedForm->title;
    }

    #[\Override]
    public function getSeoTitle(): string
    {
        return $this->hostedForm->seo_title ?: $this->getTitle().' | Polydock';
    }

    #[\Override]
    public function getSeoDescription(): string
    {
        return $this->hostedForm->seo_description
            ?: 'Provision and try a trial environment instantly with Polydock.';
    }

    /**
     * Baseline rules shared by every hosted form: contact details and an
     * allowlisted, publicly-available trial app. Concrete forms merge their
     * extra fields on top via array_merge(parent::getValidationRules(), [...]).
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
                Rule::in($this->getAllowedTrialAppUuids()),
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
        // Non-production Lagoon environments (dev/PR) aren't registered
        // domains for the reCAPTCHA site key, so the widget would only render
        // "Invalid domain for site key" — skip it there entirely. Fail closed:
        // only an EXPLICIT non-production type disables it; when the variable
        // is absent, RECAPTCHA_ENABLED alone governs.
        $lagoonEnvironmentType = config('services.recaptcha.lagoon_environment_type');

        if ($lagoonEnvironmentType !== null && $lagoonEnvironmentType !== 'production') {
            return false;
        }

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
     * Store app UUIDs this form may offer and provision, managed per form
     * record in the admin panel. Empty means the form cannot provision
     * anything, so new forms stay locked until apps are explicitly attached.
     */
    #[\Override]
    public function getAllowedTrialAppUuids(): array
    {
        return $this->hostedForm->storeApps()->pluck('uuid')->all();
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
            // ProcessUserRemoteRegistration stores the company on the
            // allocated instance from this key, not 'organization'.
            'company_name' => $validatedData['organization'] ?? '',
            'job_title' => $validatedData['job_title'] ?? '',
            'register_type' => 'REQUEST_TRIAL',
            'aup_and_privacy_acceptance' => 1,
            'opt_in_to_product_updates' => 1,
            'trial_app' => $validatedData['trial_app'] ?? null,
        ];
    }
}
