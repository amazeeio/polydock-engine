<?php

namespace App\Forms;

use App\Models\PolydockHostedForm;

interface HostedFormInterface
{
    /**
     * Get the database record this form instance is serving.
     */
    public function getHostedForm(): PolydockHostedForm;

    /**
     * Get the unique slug for the form.
     */
    public function getSlug(): string;

    /**
     * Get the descriptive title of the form.
     */
    public function getTitle(): string;

    /**
     * Get the SEO page title for the form layout.
     */
    public function getSeoTitle(): string;

    /**
     * Get the SEO page meta description.
     */
    public function getSeoDescription(): string;

    /**
     * Get the validation rules for the form payload.
     */
    public function getValidationRules(): array;

    /**
     * Get the store app UUIDs this form is allowed to provision.
     * An empty list means the form cannot provision anything.
     */
    public function getAllowedTrialAppUuids(): array;

    /**
     * Get whitelisted parent domains allowed to iframe this form.
     */
    public function getAllowedEmbedDomains(): array;

    /**
     * Check if Google reCAPTCHA is enabled for this form.
     */
    public function getRecaptchaEnabled(): bool;

    /**
     * Get the Blade template view path for rendering the form.
     */
    public function getViewName(): string;

    /**
     * Get whitelisted parent origins allowed to iframe this form (including protocol).
     */
    public function getAllowedEmbedOrigins(): array;

    /**
     * Map the form submission input array to the structure required by UserRemoteRegistration.
     */
    public function transformPayload(array $validatedData): array;
}
