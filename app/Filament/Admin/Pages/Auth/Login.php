<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages\Auth;

use Filament\Actions\Action;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

/**
 * Identifier-first login: email first; Okta-forced domains are redirected to
 * Okta OIDC and never see a password field, everyone else gets password login.
 */
class Login extends BaseLogin
{
    public bool $emailChecked = false;

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getEmailFormComponent(),
                $this->getPasswordFormComponent()->visible(fn (): bool => $this->emailChecked),
                $this->getRememberFormComponent()->visible(fn (): bool => $this->emailChecked),
            ]);
    }

    public function authenticate(): ?LoginResponse
    {
        $this->form->getState();

        if ($this->isOktaForced((string) data_get($this->data, 'email'))) {
            $this->redirect(route('okta.redirect'));

            return null;
        }

        if (! $this->emailChecked) {
            $this->emailChecked = true;

            return null;
        }

        return parent::authenticate();
    }

    protected function isOktaForced(string $email): bool
    {
        if (! config('services.okta.client_id')) {
            return false;
        }

        $domain = strtolower(Str::after($email, '@'));

        return $domain !== '' && in_array($domain, config('okta.domains', []), true);
    }

    protected function getAuthenticateFormAction(): Action
    {
        return parent::getAuthenticateFormAction()
            ->label(fn (): string => $this->emailChecked
                ? __('filament-panels::auth/pages/login.form.actions.authenticate.label')
                : 'Continue');
    }
}
