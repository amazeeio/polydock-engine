<?php

namespace App\Traits;

trait HasWebhookSensitiveData
{
    /**
     * Get the list of sensitive data keys that should be filtered from webhooks
     */
    public function getSensitiveDataKeys(): array
    {
        return
            $this->sensitiveDataKeys ?? [
                // Exact matches
                'private_key',
                'secret',
                'password',
                'token',
                'api_key',
                'ssh_key',
                'recaptcha',

                // Regex patterns (starting with /)
                '/^.*_key.*$/', // Anything containing _key
                '/^.*private.*$/', // Anything containing private
                '/^.*secret.*$/', // Anything containing secret
                '/^.*pass.*$/', // Anything containing pass
                '/^.*username.*$/', // Anything containing username
                '/^.*token.*$/', // Anything containing token
                '/^.*api.*$/', // Anything containing api
                '/^.*ssh.*$/', // Anything containing ssh
            ];
    }

    /**
     * Register additional sensitive data keys
     */
    public function registerSensitiveDataKeys(array|string $keys): self
    {
        $keys = is_array($keys) ? $keys : [$keys];
        $this->sensitiveDataKeys = array_unique(
            array_merge($this->getSensitiveDataKeys(), $keys),
        );

        return $this;
    }

    /**
     * Check if a key should be filtered
     */
    public function shouldFilterKey(string $key, array $sensitiveKeys): bool
    {
        $lowercaseKey = strtolower($key);

        foreach ($sensitiveKeys as $sensitiveKey) {
            // If it's a regex pattern
            if (str_starts_with((string) $sensitiveKey, '/')) {
                if (preg_match($sensitiveKey, $key)) {
                    return true;
                }

                continue;
            }

            // Exact match (case-insensitive)
            if (strtolower((string) $sensitiveKey) === $lowercaseKey) {
                return true;
            }

            // Contains sensitive word
            if (str_contains($lowercaseKey, strtolower((string) $sensitiveKey))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get webhook safe data by filtering sensitive information
     */
    public function getWebhookSafeData($attribute = 'data'): array
    {
        $data = $this->{$attribute} ?? [];
        $sensitiveKeys = $this->getSensitiveDataKeys();

        $retData = array_filter(
            $data,
            fn ($key) => ! $this->shouldFilterKey($key, $sensitiveKeys),
            ARRAY_FILTER_USE_KEY,
        );

        // special cases for emails that the webhook needs to be able to see the password
        if (isset($this->data['lagoon-generate-app-admin-password'])) {
            $retData['lagoon-generate-app-admin-password'] = $data['lagoon-generate-app-admin-password'];
        }

        if (isset($this->data['lagoon-generate-app-admin-username'])) {
            $retData['lagoon-generate-app-admin-username'] = $data['lagoon-generate-app-admin-username'];
        }

        // Include user information for webhooks
        if (isset($this->data['user-first-name'])) {
            $retData['user-first-name'] = $data['user-first-name'];
        }

        if (isset($this->data['user-last-name'])) {
            $retData['user-last-name'] = $data['user-last-name'];
        }

        if (isset($this->data['user-email'])) {
            $retData['user-email'] = $data['user-email'];
        }

        return $retData;
    }
}
