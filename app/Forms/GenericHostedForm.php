<?php

namespace App\Forms;

use App\Support\HostedFormHtml;

/**
 * A reusable hosted form fully defined by its database record: title,
 * description, notice, and disclaimer are set in the admin panel, so new
 * external forms need no code — just a new PolydockHostedForm record.
 *
 * The HTML fields are rendered unescaped in the view, so they pass through
 * HostedFormHtml::sanitize() here — the single choke point before output.
 */
class GenericHostedForm extends BaseHostedForm
{
    #[\Override]
    public function getViewName(): string
    {
        return 'forms.generic';
    }

    /**
     * Optional description HTML shown under the title.
     */
    public function getDescription(): ?string
    {
        return HostedFormHtml::sanitize($this->hostedForm->description);
    }

    /**
     * Optional notice HTML highlighted below the description.
     */
    public function getNotice(): ?string
    {
        return HostedFormHtml::sanitize($this->hostedForm->notice);
    }

    /**
     * Optional disclaimer HTML shown above the terms checkbox.
     */
    public function getDisclaimer(): ?string
    {
        return HostedFormHtml::sanitize($this->hostedForm->disclaimer);
    }

    #[\Override]
    public function getValidationRules(): array
    {
        return array_merge(parent::getValidationRules(), [
            'organization' => ['nullable', 'string', 'max:150'],
            'accept_terms' => ['accepted'],
        ]);
    }
}
