<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Redacts sensitive values from associative arrays.
 *
 * Extracted from HasWebhookSensitiveData to be reusable across
 * audit logging, webhooks, and Filament display contexts.
 */
class SensitiveDataRedactor
{
    public const REDACTED_VALUE = 'REDACTED';

    /**
     * Default sensitive key patterns applied when no custom keys are provided.
     *
     * @var list<string>
     */
    private const DEFAULT_SENSITIVE_KEYS = [
        // Exact matches
        'private_key',
        'secret',
        'password',
        'token',
        'api_key',
        'ssh_key',
        'recaptcha',

        // Regex patterns (starting with /)
        '/^.*_key.*$/',
        '/^.*private.*$/',
        '/^.*secret.*$/',
        '/^.*pass.*$/',
        '/^.*username.*$/',
        '/^.*token.*$/',
        '/^.*api.*$/',
        '/^.*ssh.*$/',
    ];

    /**
     * Redact sensitive values in a flat or nested array.
     *
     * Returns a new array with sensitive values replaced by 'REDACTED'.
     * Does not mutate the input.
     *
     * @param  array<string|int, mixed>  $data
     * @param  list<string>  $sensitiveKeys  Custom keys/patterns; uses defaults if empty.
     * @return array<string|int, mixed>
     */
    public static function redact(array $data, array $sensitiveKeys = []): array
    {
        $keys = $sensitiveKeys !== [] ? $sensitiveKeys : self::DEFAULT_SENSITIVE_KEYS;

        $redacted = [];

        foreach ($data as $key => $value) {
            if (\is_string($key) && self::shouldRedactKey($key, $keys)) {
                $redacted[$key] = self::REDACTED_VALUE;
            } elseif (\is_array($value)) {
                $redacted[$key] = self::redact($value, $keys);
            } else {
                $redacted[$key] = $value;
            }
        }

        return $redacted;
    }

    /**
     * Determine if a key matches any sensitive pattern.
     *
     * @param  list<string>  $sensitiveKeys
     */
    public static function shouldRedactKey(string $key, array $sensitiveKeys = []): bool
    {
        $keys = $sensitiveKeys !== [] ? $sensitiveKeys : self::DEFAULT_SENSITIVE_KEYS;
        $lowercaseKey = strtolower($key);

        foreach ($keys as $sensitiveKey) {
            // Regex pattern
            if (str_starts_with($sensitiveKey, '/')) {
                if (preg_match($sensitiveKey, $lowercaseKey)) {
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
     * Get the default sensitive keys list.
     *
     * @return list<string>
     */
    public static function defaultSensitiveKeys(): array
    {
        return self::DEFAULT_SENSITIVE_KEYS;
    }
}
