# 001 — Deployment-tracking data model

- **Priority:** P1 (foundation for the whole feature)
- **Effort:** M
- **Risk:** Low (additive schema only)
- **Dependencies:** none
- **Category:** feature / schema
- **Planned at:** 2026-07-01

## Why this matters

Today the "last deployment" for an instance is fetched **live from Lagoon** inside
the Filament `trigger_deploy` action (`getProjectEnvironmentDeployments`). That is
fine for a single view page but cannot render on a list of hundreds/thousands of
instances, cannot be filtered/sorted, and gives us nowhere to record a scheduled
redeploy, an in-flight bulk deploy, or a cadence. We need persistent state:

1. **Per-store-app cadence config** — how often, and whether enabled.
2. **A deployment-run record** — one row per triggered rollout (manual or
   scheduled), holding the Lagoon `bulkId`, trigger source, and roll-up counts.
3. **Cached per-instance deploy state** — last deploy name/status/time + a
   `next_redeploy_at` the scheduler can query on an index.
4. **A beta flag on `UserGroup`** — so beta groups can get a shorter cadence.

This plan is schema + models only. No behaviour changes; nothing reads/writes the
new fields yet (that's 002/003/004).

## Scope

**In scope:** migrations, model fillable/casts/relationships, factory updates.
**Out of scope:** any deploy logic, scheduler, UI, polling. No changes to the
`PolydockAppInstanceStatus` enum.

**Files to touch:**
- `database/migrations/` (new migrations, timestamp-prefixed after the latest)
- `app/Models/PolydockStoreApp.php`
- `app/Models/PolydockAppInstance.php`
- `app/Models/UserGroup.php`
- `app/Models/PolydockDeploymentRun.php` (new)
- `database/factories/` (matching factory for the new model; extend existing)

## Commands you will need

```bash
php artisan make:model PolydockDeploymentRun -mf   # model + migration + factory
php artisan migrate                                 # apply
php artisan test --filter=DeploymentModel           # the tests added below
./vendor/bin/pint app/Models database/migrations
```

## Schema

### New table `polydock_deployment_runs`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint pk | |
| `uuid` | uuid, indexed | match project convention for other models |
| `polydock_store_app_id` | FK → polydock_store_apps, nullable | null = mixed/ad-hoc run |
| `trigger_source` | string | `scheduled`, `manual`, `beta` (use a small enum/const set) |
| `triggered_by_user_id` | FK → users, nullable | who fired a manual run |
| `lagoon_bulk_id` | string, nullable, indexed | returned by `bulkDeployEnvironments` |
| `status` | string | `pending`, `running`, `completed`, `partial_failed`, `failed` |
| `total_count` | unsigned int, default 0 | instances targeted |
| `success_count` | unsigned int, default 0 | |
| `failed_count` | unsigned int, default 0 | |
| `started_at` | timestamp, nullable | |
| `completed_at` | timestamp, nullable | |
| `last_polled_at` | timestamp, nullable | for poll backoff |
| `poll_attempts` | unsigned int, default 0 | mirror purge attempt pattern |
| timestamps | | |

### Add to `polydock_app_instances`

| Column | Type | Notes |
|---|---|---|
| `deployment_run_id` | FK → polydock_deployment_runs, nullable | the run that last touched it |
| `last_deployment_name` | string, nullable | Lagoon build name e.g. `lagoon-build-abc` |
| `last_deployment_status` | string, nullable | `new`/`building`/`complete`/`failed` (Lagoon values) |
| `last_deployed_at` | timestamp, nullable | Lagoon deployment `completed` time |
| `last_deploy_triggered_at` | timestamp, nullable | when *we* fired it |
| `next_redeploy_at` | timestamp, nullable | when cadence says redeploy next |

Add index `(next_redeploy_at)` and a composite `(polydock_store_app_id, next_redeploy_at)`
so the scheduler (plan 003) can select due instances grouped by app efficiently —
mirror the existing `(status, next_poll_after)` index.

### Add to `polydock_store_apps`

| Column | Type | Notes |
|---|---|---|
| `redeploy_enabled` | boolean, default false | master switch for auto cadence |
| `redeploy_interval_days` | unsigned int, nullable | default cadence, e.g. 7 |
| `beta_redeploy_interval_days` | unsigned int, nullable | cadence for beta groups (shorter) |

(Store as real columns, not inside the `app_config` JSON — the scheduler filters on
them and JSON filtering is awkward and unindexed.)

### Add to `user_groups`

| Column | Type | Notes |
|---|---|---|
| `is_beta` | boolean, default false, indexed | beta cohort flag |

## Model changes

- **`PolydockDeploymentRun`**: `$fillable`/`$casts` (timestamps → datetime), `uuid`
  auto-set on creating (copy the boot pattern from an existing model), relations:
  `storeApp()` belongsTo, `instances()` hasMany `PolydockAppInstance`,
  `triggeredByUser()` belongsTo. Add a `const` set for `trigger_source` and
  `status` values (or backing enums, matching how the repo does small enums).
- **`PolydockAppInstance`**: add the new columns to `$fillable`/`$casts`
  (`last_deployed_at`, `last_deploy_triggered_at`, `next_redeploy_at` as datetime),
  and `deploymentRun()` belongsTo. Do **not** add anything to the status enum.
- **`PolydockStoreApp`**: add the three columns to `$fillable`/`$casts`
  (`redeploy_enabled` bool, intervals int). Add a helper
  `effectiveRedeployIntervalDays(bool $isBeta): ?int` returning the beta interval
  when `$isBeta` and set, else the default — plan 003 depends on this.
- **`UserGroup`**: add `is_beta` to `$fillable`/`$casts` (bool).

## Steps

1. Create the migration for `polydock_deployment_runs` (via `make:model -mf`).
   **Verify:** `php artisan migrate` runs clean; `php artisan migrate:rollback`
   drops it clean.
2. Create a second migration adding the instance columns + indexes.
   **Verify:** re-run migrate up/down; confirm indexes exist
   (`php artisan db:show --counts` or inspect the schema).
3. Create a third migration adding the store-app columns and the `user_groups.is_beta`
   column. **Verify:** up/down clean.
4. Update the four models as described. **Verify:** `php artisan tinker` — create a
   `PolydockDeploymentRun`, attach an instance, read back the relations.
5. Update/add factories so tests can build a run + instances. **Verify:** factory
   `create()` works in tinker.

## Test plan

Add `tests/Feature/Deployment/DeploymentModelTest.php`:
- A `PolydockDeploymentRun` can be created, gets a uuid, and `instances()` /
  `storeApp()` / `triggeredByUser()` relations resolve.
- `PolydockStoreApp::effectiveRedeployIntervalDays()` returns beta interval when
  `is_beta` and set, default otherwise, `null` when neither set.
- New instance/store-app/user-group columns are fillable and cast correctly
  (dates come back as Carbon, bools as bool).

## Done criteria

- [ ] All three migrations apply and roll back cleanly.
- [ ] Four models updated; relations resolve in tinker.
- [ ] `php artisan test --filter=DeploymentModel` passes.
- [ ] `./vendor/bin/pint --test` clean on touched files.
- [ ] No change to `PolydockAppInstanceStatus` or any lifecycle job.

## STOP conditions

- If a migration would need to **modify** (not add) an existing column, stop and
  report — this plan is additive only.
- If the repo uses PHP backed enums for model attributes elsewhere and you're
  unsure whether to match that vs. plain string constants, report the two options
  rather than guessing.

## Maintenance notes

- `next_redeploy_at` is denormalised cadence state; plan 003 owns keeping it
  correct. If cadence config changes on a store app, 003 must recompute it.
- Deployment-run roll-up counts are eventually-consistent (updated by the poll job
  in 002); treat them as display values, not a source of truth for billing.
