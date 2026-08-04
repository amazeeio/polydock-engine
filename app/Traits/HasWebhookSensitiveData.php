<?php

namespace App\Traits;

use App\Support\SensitiveDataRedactor;

trait HasWebhookSensitiveData
{
    /**
     * Get the list of sensitive data keys that should be filtered from webhooks
     *
     * @return array<mixed>
     */
    public function getSensitiveDataKeys(): array
    {
        return $this->sensitiveDataKeys ?? SensitiveDataRedactor::defaultSensitiveKeys();
    }

    /**
     * Register additional sensitive data keys
     *
     * @param  list<string>  $keys
     */
    public function registerSensitiveDataKeys(array|string $keys): self
    {
        $keys = \is_array($keys) ? $keys : [$keys];
        $this->sensitiveDataKeys = array_unique([
            ...$this->getSensitiveDataKeys(),
            ...$keys,
        ]);

        return $this;
    }

    /**
     * Check if a key should be filtered
     *
     * @param  list<string>  $sensitiveKeys
     */
    public function shouldFilterKey(string $key, array $sensitiveKeys): bool
    {
        return SensitiveDataRedactor::shouldRedactKey($key, $sensitiveKeys);
    }

    /**
     * Get webhook safe data by filtering sensitive information.
     *
     * When $includeCredentials is true, the generated app admin credentials are
     * re-added after redaction — the trial email consumers need them. Webhooks
     * that only observe events (e.g. event logging) should pass false.
     *
     * @return array<string, mixed>
     */
    public function getWebhookSafeData(string $attribute = 'data', bool $includeCredentials = true): array
    {
        $data = $this->{$attribute} ?? [];
        $sensitiveKeys = $this->getSensitiveDataKeys();

        $retData = array_filter(
            $data,
            fn ($key) => ! $this->shouldFilterKey($key, $sensitiveKeys),
            ARRAY_FILTER_USE_KEY,
        );

        // special cases for emails that the webhook needs to be able to see the password
        if ($includeCredentials && isset($data['lagoon-generate-app-admin-password'])) {
            $retData['lagoon-generate-app-admin-password'] = $data['lagoon-generate-app-admin-password'];
        }

        if ($includeCredentials && isset($data['lagoon-generate-app-admin-username'])) {
            $retData['lagoon-generate-app-admin-username'] = $data['lagoon-generate-app-admin-username'];
        }

        // Include user information for webhooks
        if (isset($data['user-first-name'])) {
            $retData['user-first-name'] = $data['user-first-name'];
        }

        if (isset($data['user-last-name'])) {
            $retData['user-last-name'] = $data['user-last-name'];
        }

        if (isset($data['user-email'])) {
            $retData['user-email'] = $data['user-email'];
        }

        return $retData;
    }
}
