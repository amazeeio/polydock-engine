<?php

namespace App\Forms;

use App\Enums\PolydockStoreAppStatusEnum;
use App\Enums\PolydockStoreStatusEnum;
use App\Rules\BannedEmail;
use Illuminate\Validation\Rule;
use Override;

class DrupalAIDemoDrupalOrgForm extends BaseHostedForm
{
    #[Override]
    public function getSlug(): string
    {
        return 'drupal-ai-demo';
    }

    #[Override]
    public function getTitle(): string
    {
        return 'Private Drupal AI Demo on drupal.org';
    }

    #[Override]
    public function getSeoTitle(): string
    {
        return 'Drupal AI Demo on drupal.org by amazee.ai';
    }

    #[Override]
    public function getSeoDescription(): string
    {
        return 'Try Drupal AI with our custom demo experiences designed for developers and content editors new to Drupal AI on drupal.org.';
    }

    #[Override]
    public function getViewName(): string
    {
        return 'forms.drupal-ai-demo';
    }

    #[Override]
    public function getValidationRules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', new BannedEmail],
            'organization' => ['nullable', 'string', 'max:150'],
            'job_title' => ['nullable', 'string', 'max:150'],
            'country' => [
                'nullable',
                'string',
                Rule::in(array_values(__('filament-country-field::country', [], 'en'))),
            ],
            'stage_in_ai_adoption' => ['nullable', 'string', 'in:just-curious,specific-need,already-using'],
            'interest_in_drupal_ai' => ['nullable', 'string', 'max:255'],
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

    #[Override]
    public function transformPayload(array $validatedData): array
    {
        $payload = parent::transformPayload($validatedData);

        // Add custom properties specific to the Drupal AI demo setup
        $payload['company_name'] = $validatedData['organization'] ?? '';
        $payload['instance_config_stage_in_ai_adoption'] = $validatedData['stage_in_ai_adoption'] ?? '';
        $payload['instance_config_interest_in_drupal_ai'] = $validatedData['interest_in_drupal_ai'] ?? '';
        $payload['instance_config_country'] = $validatedData['country'] ?? '';

        return $payload;
    }
}
