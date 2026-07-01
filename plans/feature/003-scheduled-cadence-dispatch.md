# 003 — Scheduled cadence dispatch (cron, throttle, beta)

- **Priority:** P1
- **Effort:** M
- **Risk:** Medium (fires real deploys across many instances)
- **Dependencies:** 001, 002
- **Category:** feature / scheduler
- **Planned at:** 2026-07-01

## Why this matters

The point of the feature: apps redeploy **automatically** on a cadence defined per
store app (e.g. weekly), beta groups redeploy more often, and 1000 due apps don't
all fire at once. This plan adds the scheduled command that selects due instances,
throttles them, and calls the service from 002.

## Scope

**In scope:**
- `app/Console/Commands/DispatchScheduledRedeploysCommand.php` (new).
- Schedule wiring in `routes/console.php`.
- `next_redeploy_at` computation/backfill logic.

**Out of scope:** the deploy/poll mechanics (002 owns them), UI (004).

## Behaviour

### Selecting due instances

Query instances where:
- `status` in `RUNNING_HEALTHY_UNCLAIMED`, `RUNNING_HEALTHY_CLAIMED` (uses eligible
  set from 002; **trials excluded** — filter out `is_trial = true`),
- their store app has `redeploy_enabled = true`,
- `next_redeploy_at <= now()` **or** `next_redeploy_at is null` (first-time),
- no in-flight `deployment_run` (belt-and-braces; 002 also guards this).

Order by `next_redeploy_at asc` and cap at `config('polydock.deploy.max_per_run')`
(mirror `purge_max_per_run`). This per-run cap + a frequent schedule (see below) is
the primary throttle — it bounds how many new builds we create per tick.

### Grouping + firing

- Group the selected instances **by store app** and hand each group to
  `PolydockDeploymentService::redeploy($group, 'scheduled')`.
- **Forward-compat for deployment windows (plan 005):** structure the fire step so
  there is a single, obvious place to insert a "is now inside this instance's
  allowed deployment window?" check *before* calling the service. An instance that
  fails the window check is simply **not fired this tick** — it stays due
  (`next_redeploy_at` unchanged) and is naturally re-evaluated on a later tick that
  falls in-window. Do not build the window logic now; just don't fuse the
  selection, firing, and cadence-advance steps so tightly that a gate can't slot in.
- Within the service, `bulkDeployEnvironments` calls are chunked at
  `config('polydock.deploy.bulk_chunk_size')` tuples so one mutation never carries
  thousands of environments. (If the "confirm Lagoon build concurrency" open item
  resolves to "Lagoon has no cluster-side cap", tighten `max_per_run` accordingly.)

### Cadence bookkeeping

After a successful trigger, set each instance's `next_redeploy_at = now() +
effectiveRedeployIntervalDays(isBeta)` where `isBeta` comes from the instance's
`userGroup->is_beta`. Add **jitter** (e.g. spread within a window, derived
deterministically from the instance id — do **not** use random, and note
`Date::now()`-only helpers) so a whole cohort doesn't re-converge on the same
tick next cycle.

### Scheduling frequency

In `routes/console.php`, schedule `everyTenMinutes()` (or similar) with
`withoutOverlapping()` and `onOneServer()`, matching the purge command's style. The
short interval + per-run cap means a large backlog drains smoothly over many ticks
rather than in one spike.

## Steps

1. Write the command's selection query. **Verify:** a feature test seeds mixed
   instances (eligible/ineligible/trial/disabled-app/in-flight) and asserts only
   the right ones are selected, capped at `max_per_run`.
2. Wire grouping-by-store-app → `redeploy(..., 'scheduled')`. **Verify:** test with
   a fake service asserts one run per store app and the right instances per group.
3. Implement `next_redeploy_at` update + beta interval + jitter. **Verify:** test
   that a beta-group instance gets the beta interval, a normal one the default, and
   two instances of the same cohort get *different* `next_redeploy_at` (jitter).
4. Add the schedule entry. **Verify:** `php artisan schedule:list` shows it with
   `withoutOverlapping`/`onOneServer`.
5. Add the config keys' defaults to `config/polydock.php`.

## Test plan

`tests/Feature/Deployment/ScheduledRedeployCommandTest.php`:
- Only eligible, enabled, due, non-trial, non-in-flight instances are picked.
- Respects `max_per_run`.
- Groups by store app; one `redeploy` call per group.
- Beta group → beta interval; default otherwise; both push `next_redeploy_at` forward.
- Jitter: same-cohort instances don't all get an identical `next_redeploy_at`.
- Null-vs-past `next_redeploy_at` both count as due.

Follow the style of the existing trial/pre-warm command tests (plan ../008 added
these) and fake the deployment service.

## Done criteria

- [ ] Command selects correctly, caps per run, groups by store app, excludes trials.
- [ ] Cadence advances with beta override + jitter.
- [ ] Scheduled in `routes/console.php` with overlap/one-server guards.
- [ ] Tests pass; `pint --test` clean.

## STOP conditions

- If excluding trials conflicts with an existing "keep pool fresh" expectation for
  unallocated pre-warm instances (some pre-warm instances may be flagged trial-like),
  stop and report — the eligibility filter must not accidentally exclude the
  unallocated pool.
- If `Date`/time helpers needed for jitter are restricted in this codebase's testing
  setup, report and use an injectable clock instead of guessing.

## Maintenance notes

- `max_per_run` × schedule frequency = max new builds/hour. Document the chosen
  numbers in `config/polydock.php` comments so ops can tune throughput vs. Lagoon load.
- Changing a store app's `redeploy_interval_days` does not retroactively rewrite
  existing `next_redeploy_at`; the next fire recomputes from the new value. If
  immediate effect is wanted, add an admin action (004) to null out `next_redeploy_at`.
