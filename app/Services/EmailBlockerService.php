<?php

namespace App\Services;

use App\Models\PolydockBannedPattern;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EmailBlockerService
{
    private const string FILE_NAME = 'disposable_domains.json';

    private ?array $disposableDomainsCache = null;

    /**
     * Check if an email is blocked.
     */
    public function checkEmail(string $email): EmailBlockerResult
    {
        $email = trim(strtolower($email));

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return new EmailBlockerResult(true, 'Invalid email format');
        }

        // 1. Check manual database bans
        $bannedPattern = PolydockBannedPattern::whereRaw(
            "? LIKE REPLACE(REPLACE(REPLACE(REPLACE(pattern, '=', '=='), '%', '=%'), '_', '=_'), '*', '%') ESCAPE '='",
            [$email]
        )->first();
        if ($bannedPattern) {
            return new EmailBlockerResult(
                true,
                $bannedPattern->reason ?: 'Manually banned'
            );
        }

        // 2. Check disposable email domains list
        $emailParts = explode('@', $email);
        $domain = $emailParts[1] ?? '';

        if (! empty($domain)) {
            $disposableDomains = $this->loadDisposableDomains();
            $domainHierarchy = $this->getDomainHierarchy($domain);

            foreach ($domainHierarchy as $subDomain) {
                if (isset($disposableDomains[$subDomain])) {
                    return new EmailBlockerResult(true, 'Disposable email address');
                }
            }
        }

        return new EmailBlockerResult(false);
    }

    /**
     * Update disposable domains list from the GitHub source.
     */
    public function updateDisposableDomains(): int
    {
        try {
            $url = 'https://raw.githubusercontent.com/disposable/disposable-email-domains/master/index.json';
            $response = Http::timeout(10)->get($url);

            if ($response->successful()) {
                $domains = $response->json();

                if (is_array($domains) && ! empty($domains)) {
                    $this->saveDisposableDomains($domains);
                    Log::info('Successfully updated disposable email domains list', ['count' => count($domains)]);

                    return count($domains);
                }
            }

            Log::warning('Failed to update disposable email domains list: response not successful or malformed JSON.');
        } catch (\Exception $e) {
            Log::error('Exception triggered while updating disposable email domains', ['message' => $e->getMessage()]);
        }

        return 0;
    }

    /**
     * Load disposable domains from local storage fallback.
     */
    private function loadDisposableDomains(): array
    {
        if ($this->disposableDomainsCache !== null) {
            return $this->disposableDomainsCache;
        }

        $path = storage_path('app/'.self::FILE_NAME);

        if (! file_exists($path)) {
            $this->disposableDomainsCache = [];

            return [];
        }

        $content = @file_get_contents($path);

        if ($content === false) {
            Log::error('Failed to load disposable domains from storage: file could not be read.', ['path' => $path]);
            $this->disposableDomainsCache = [];

            return [];
        }

        $domains = json_decode($content, true);

        if (! is_array($domains)) {
            Log::error('Failed to load disposable domains from storage: malformed JSON.', ['path' => $path]);
            $this->disposableDomainsCache = [];

            return [];
        }

        $this->disposableDomainsCache = array_fill_keys($domains, true);

        return $this->disposableDomainsCache;
    }

    /**
     * Save disposable domains to local storage.
     */
    private function saveDisposableDomains(array $domains): void
    {
        $path = storage_path('app/'.self::FILE_NAME);

        $json = json_encode(array_values(array_unique($domains)), JSON_PRETTY_PRINT);

        if ($json === false) {
            Log::error('Failed to encode disposable domains to JSON.');

            return;
        }

        $result = @file_put_contents($path, $json);

        if ($result === false) {
            Log::error('Failed to write disposable domains to storage.', ['path' => $path]);
        } else {
            $this->disposableDomainsCache = array_fill_keys($domains, true);
        }
    }

    /**
     * Get domain hierarchy for checking parent domains (e.g. sub.domain.com -> [sub.domain.com, domain.com]).
     */
    private function getDomainHierarchy(string $domain): array
    {
        $parts = explode('.', strtolower($domain));
        $hierarchy = [];

        while (count($parts) >= 2) {
            $hierarchy[] = implode('.', $parts);
            array_shift($parts);
        }

        return $hierarchy;
    }
}
