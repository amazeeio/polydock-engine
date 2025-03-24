<?php

namespace App\Traits;

trait HasWebhookSensitiveData
{
    /**
     * Get the list of sensitive data keys that should be filtered from webhooks
     */
    public function getSensitiveDataKeys(): array
    {
        return $this->sensitiveDataKeys ?? [
            // Exact matches
            'private_key',
            'secret',
            'password',
            'token',
            'api_key',
            'ssh_key',
            
            // Regex patterns (starting with /)
            '/^.*_key.*$/',          // Anything containing _key
            '/^.*private.*$/',       // Anything containing private
            '/^.*secret.*$/',       // Anything containing secret
            '/^.*pass.*$/',          // Anything containing pass
            '/^.*username.*$/',      // Anything containing username
            '/^.*token.*$/',         // Anything containing token
            '/^.*api.*$/',           // Anything containing api
            '/^.*ssh.*$/',           // Anything containing ssh
        ];
    }

    /**
     * Register additional sensitive data keys
     */
    public function registerSensitiveDataKeys(array|string $keys): self
    {
        $keys = is_array($keys) ? $keys : [$keys];
        $this->sensitiveDataKeys = array_unique(
            array_merge($this->getSensitiveDataKeys(), $keys)
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
            if (str_starts_with($sensitiveKey, '/')) {
                if (preg_match($sensitiveKey, $key)) {
                    return true;
                }
                continue;
            }

            // Exact match (case-insensitive)
            if (strtolower($sensitiveKey) === $lowercaseKey) {
                return true;
            }

            // Contains sensitive word
            if (str_contains($lowercaseKey, strtolower($sensitiveKey))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get webhook safe data by filtering sensitive information
     */
    public function getWebhookSafeData(): array
    {
        $data = $this->data ?? [];
        $sensitiveKeys = $this->getSensitiveDataKeys();

        $retData = array_filter(
            $data,
            fn($key) => !$this->shouldFilterKey($key, $sensitiveKeys),
            ARRAY_FILTER_USE_KEY
        );

        if($this->data['lagoon-generate-app-admin-password']) {
            $retData['lagoon-generate-app-admin-password'] = $data['lagoon-generate-app-admin-password'];
        }

        if($this->data['lagoon-generate-app-admin-username']) {
            $retData['lagoon-generate-app-admin-username'] = $data['lagoon-generate-app-admin-username'];
        }

        return $retData;
    }
} 