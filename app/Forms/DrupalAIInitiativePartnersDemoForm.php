<?php

namespace App\Forms;

class DrupalAIInitiativePartnersDemoForm extends BaseHostedForm
{
    #[\Override]
    public function getSlug(): string
    {
        return 'drupal-ai-partners-demo';
    }

    #[\Override]
    public function getTitle(): string
    {
        return 'Drupal AI Initiative - Partners Demo';
    }

    #[\Override]
    public function getSeoTitle(): string
    {
        return 'Drupal AI Initiative - Partners Demo by amazee.io';
    }

    #[\Override]
    public function getSeoDescription(): string
    {
        return 'Spin up a new amazee.io hosted demo of the Drupal AI partners demo for members of the Drupal AI initiative.';
    }

    #[\Override]
    public function getViewName(): string
    {
        return 'forms.drupal-ai-partners-demo';
    }

    #[\Override]
    public function getValidationRules(): array
    {
        return array_merge(parent::getValidationRules(), [
            'accept_terms' => ['accepted'],
        ]);
    }
}
