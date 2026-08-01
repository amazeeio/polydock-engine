<?php

namespace App\Forms;

use Illuminate\Validation\Rule;

#[FormLabel('Drupal AI Demo (drupal.org)')]
class DrupalAIDemoDrupalOrgForm extends BaseHostedForm
{
    #[\Override]
    public function getViewName(): string
    {
        return 'forms.drupal-ai-demo';
    }

    /**
     * @return array<mixed>
     */
    #[\Override]
    public function getAllowedEmbedDomains(): array
    {
        return [
            ...parent::getAllowedEmbedDomains(),
            'drupal.org',
            'www.drupal.org',
            'new.drupal.org',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    #[\Override]
    public function getValidationRules(): array
    {
        return [
            ...parent::getValidationRules(),
            'organization' => ['nullable', 'string', 'max:150'],
            'job_title' => ['nullable', 'string', 'max:150'],
            'country' => [
                'nullable',
                'string',
                Rule::in(array_values(__('filament-country-field::country', [], 'en'))),
            ],
            'stage_in_ai_adoption' => ['nullable', 'string', 'in:just-curious,specific-need,already-using'],
            'interest_in_drupal_ai' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @param  array<string, mixed>  $validatedData
     * @return array<string, mixed>
     */
    #[\Override]
    public function transformPayload(array $validatedData): array
    {
        $payload = parent::transformPayload($validatedData);

        // Add custom properties specific to the Drupal AI demo setup
        $payload['instance_config_stage_in_ai_adoption'] = $validatedData['stage_in_ai_adoption'] ?? '';
        $payload['instance_config_interest_in_drupal_ai'] = $validatedData['interest_in_drupal_ai'] ?? '';
        $payload['instance_config_country'] = $validatedData['country'] ?? '';

        return $payload;
    }
}
