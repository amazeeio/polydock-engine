# Audit Trail

## Overview

Polydock Engine uses [spatie/laravel-activitylog](https://spatie.be/docs/laravel-activitylog/v4/introduction) to record an immutable audit trail of user actions. Every activity row answers: **who did what, when, to which resource, and with what before/after values.**

## Schema (`activity_log` table)

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint | Primary key |
| `log_name` | string | Log channel (default: `audit`) |
| `description` | text | Human-readable action description |
| `subject_type` | string (nullable) | Polymorphic model class of the resource acted upon |
| `subject_id` | bigint (nullable) | ID of the subject |
| `causer_type` | string (nullable) | Always `App\Models\User` when set |
| `causer_id` | bigint (nullable) | User ID of the actor |
| `properties` | JSON | Structured payload (see below) |
| `event` | string (nullable) | Eloquent event (`created`, `updated`, `deleted`) |
| `batch_uuid` | uuid (nullable) | Groups related activities in a single batch |
| `created_at` | timestamp | When the action occurred |
| `updated_at` | timestamp | |

## Properties JSON structure

Every activity row has system-context fields automatically injected:

```json
{
  "ip": "203.0.113.42",
  "user_agent": "GuzzleHttp/7 (MOAD Orchestrator)",
  "request_id": "req_abc123",
  "token_id": 7,
  "token_name": "moad-service-token",
  "is_service_account": true,
  "old": { "status": "running-healthy-claimed" },
  "attributes": { "status": "pending-pre-remove" }
}
```

| Field | Source | Always present |
|-------|--------|----------------|
| `ip` | `request()->ip()` | Yes (may be `127.0.0.1` in console) |
| `user_agent` | `request()->userAgent()` (truncated 500 chars) | Yes |
| `request_id` | `X-Request-Id` header | Only if header sent |
| `token_id` | Sanctum `PersonalAccessToken.id` | Only for token-auth requests |
| `token_name` | Sanctum token name | Only for token-auth requests |
| `is_service_account` | `$user->hasRole('service-account')` | Always (bool) |

## Configuration

Environment variables:

```env
ACTIVITY_LOGGER_ENABLED=true          # Set false to disable all logging
ACTIVITY_LOGGER_DELETE_AFTER_DAYS=365  # Retention period for activitylog:clean
```

Config file: `config/activitylog.php`

## Retention / Pruning

A scheduled job runs daily:

```php
Schedule::command('activitylog:clean')->daily()->onOneServer();
```

It deletes all `activity_log` rows older than `ACTIVITY_LOGGER_DELETE_AFTER_DAYS` (default 365). The `onOneServer()` ensures it runs once even in multi-worker deployments.

Manual run:

```bash
php artisan activitylog:clean
```

## Redaction

All properties are run through `App\Support\SensitiveDataRedactor` before persisting. This is defense-in-depth: even if a developer accidentally passes sensitive data in `withProperties()`, it will be redacted.

Sensitive patterns (case-insensitive, supports regex):

- `password`, `secret`, `token`, `api_key`, `ssh_key`, `private_key`, `recaptcha`
- `/^.*_key.*$/`, `/^.*private.*$/`, `/^.*secret.*$/`, `/^.*pass.*$/`, `/^.*username.*$/`, `/^.*token.*$/`, `/^.*api.*$/`, `/^.*ssh.*$/`

System-context keys (`token_id`, `token_name`, `ip`, `user_agent`, `request_id`, `is_service_account`) are **exempt** from redaction.

### Using the redactor elsewhere

```php
use App\Support\SensitiveDataRedactor;

// Redact with default patterns
$safe = SensitiveDataRedactor::redact($data);

// Check a single key
SensitiveDataRedactor::shouldRedactKey('my_api_key'); // true

// Custom patterns
$safe = SensitiveDataRedactor::redact($data, ['custom_field', '/^internal_.*/']);
```

## Querying the audit log

```php
use Spatie\Activitylog\Models\Activity;

// All activities for an instance
$activities = Activity::where('subject_type', PolydockAppInstance::class)
    ->where('subject_id', $instance->id)
    ->latest()
    ->get();

// All activities by a specific user
$activities = Activity::where('causer_id', $user->id)->latest()->get();

// All service-account actions
$activities = Activity::where('properties->is_service_account', true)->latest()->get();

// All actions from a specific token
$activities = Activity::where('properties->token_name', 'moad-service-token')->latest()->get();

// Activities in the last 24 hours
$activities = Activity::where('created_at', '>=', now()->subDay())->latest()->get();
```

## How to log a new action (PR 2+)

### Option A: Model trait (automatic for CRUD)

```php
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class MyModel extends Model
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->logExcept(['updated_at']);
    }
}
```

### Option B: Manual logging (for actions without field changes)

```php
activity('audit')
    ->performedOn($instance)
    ->causedBy(auth()->user())
    ->withProperties(['action' => 'force_full_delete', 'reason' => $reason])
    ->log('Force purge triggered');
```

The `ActivityLogServiceProvider` will automatically inject token identity, IP, user-agent, and service-account flag onto every row regardless of how it was created.

## Roadmap

- **PR 2**: Add `LogsActivity` trait to all core models, manual audit calls in API controller
- **PR 3**: Filament admin UI (per-resource Activity tab + global Audit Log page)
