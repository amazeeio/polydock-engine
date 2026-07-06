<?php

declare(strict_types=1);

namespace App\Auth;

use Laravel\Socialite\Two\InvalidStateException;
use SocialiteProviders\Manager\OAuth2\User;
use SocialiteProviders\Okta\Provider as OktaProvider;

/**
 * Dev-only fake Okta IdP for local end-to-end testing of the SSO flow
 * (OKTA_FAKE=true, never in production). Redirects to a local form instead
 * of Okta and builds the user from the submitted fields — the login page,
 * callback controller, JIT provisioning and group sync all run unchanged.
 * Swap in the real Okta app by unsetting OKTA_FAKE; no code changes.
 */
class FakeOktaProvider extends OktaProvider
{
    protected function getAuthUrl($state): string
    {
        return route('fake-okta.form', ['state' => $state]);
    }

    public function user()
    {
        if ($this->hasInvalidState()) {
            throw new InvalidStateException;
        }

        $email = strtolower((string) $this->request->query('email'));

        /** @var User */
        return $this->mapUserToObject([
            'sub' => (string) ($this->request->query('sub') ?: 'fake-okta|'.$email),
            'email' => $email,
            'given_name' => (string) $this->request->query('given_name'),
            'family_name' => (string) $this->request->query('family_name'),
            'name' => trim($this->request->query('given_name').' '.$this->request->query('family_name')),
            'groups' => array_values(array_filter((array) $this->request->query('groups', []))),
        ]);
    }
}
