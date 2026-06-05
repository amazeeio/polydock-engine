<?php

declare(strict_types=1);

namespace App\Providers;

use App\Support\SensitiveDataRedactor;
use Illuminate\Support\ServiceProvider;
use Spatie\Activitylog\Models\Activity;

class ActivityLogServiceProvider extends ServiceProvider
{
    /**
     * System-context keys that are added by this provider and must not be redacted.
     * These are safe -- they come from the system, not from user input.
     */
    private const CONTEXT_KEYS = [
        'ip',
        'user_agent',
        'request_id',
        'token_id',
        'token_name',
        'is_service_account',
    ];

    public function boot(): void
    {
        Activity::creating(function (Activity $activity): void {
            // Order matters: redact user-supplied properties first,
            // then attach system context (which should never be redacted).
            $this->redactProperties($activity);
            $this->attachRequestContext($activity);
        });
    }

    /**
     * Attach request-level context (token identity, IP, user-agent) to every activity row.
     */
    private function attachRequestContext(Activity $activity): void
    {
        $properties = $activity->properties?->toArray() ?? [];

        $request = rescue(fn () => request(), null, false);

        if ($request !== null) {
            $properties['ip'] = $request->ip();
            $properties['user_agent'] = mb_substr((string) $request->userAgent(), 0, 500);

            $requestId = $request->header('X-Request-Id');
            if ($requestId !== null) {
                $properties['request_id'] = mb_substr((string) $requestId, 0, 255);
            }
        }

        // Attach Sanctum token identity when available
        $user = $activity->causer;
        if ($user !== null && method_exists($user, 'currentAccessToken')) {
            $token = $user->currentAccessToken();
            if ($token instanceof \Laravel\Sanctum\PersonalAccessToken) {
                $properties['token_id'] = $token->id;
                $properties['token_name'] = $token->name;
            }
        }

        // Always record whether the causer is a service-account
        $properties['is_service_account'] = $user !== null
            && method_exists($user, 'hasRole')
            && $user->hasRole('service-account');

        $activity->properties = collect($properties);
    }

    /**
     * Run stored properties through the sensitive data redactor as defense-in-depth.
     * Skips system-context keys that we attach ourselves.
     */
    private function redactProperties(Activity $activity): void
    {
        $properties = $activity->properties?->toArray() ?? [];

        if ($properties === []) {
            return;
        }

        // Preserve any context keys that might already be set (e.g. from batch operations)
        $preserved = [];
        foreach (self::CONTEXT_KEYS as $key) {
            if (\array_key_exists($key, $properties)) {
                $preserved[$key] = $properties[$key];
                unset($properties[$key]);
            }
        }

        $redacted = SensitiveDataRedactor::redact($properties);

        $activity->properties = collect(array_merge($redacted, $preserved));
    }
}
