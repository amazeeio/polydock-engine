# 002 — Redeploy service + poll (persist deploy state)

- **Priority:** P1
- **Effort:** M/L
- **Risk:** Medium (talks to Lagoon; must be idempotent and non-destructive)
- **Dependencies:** 001
- **Category:** feature / service
- **Planned at:** 2026-07-01

## Why this matters

We have Lagoon client methods and ad-hoc commands (`TriggerLagoonDeployOnAppInstance`,
`TriggerLagoonDeployOnAppInstances`, `PollDeploymentStatusCommand`), but nothing
**persists** deploy state to the model added in 001. This plan introduces one
service that (a) triggers a redeploy for a set of instances, creating a
`PolydockDeploymentRun`, and (b) polls the run's `bulkId` to fill in per-instance
`last_deployment_*` fields and the run's counts. Both the scheduler (003) and the
admin bulk action (004) call this service — no duplicated deploy logic.

## Scope

**In scope:**
- New `app/Services/PolydockDeploymentService.php` (or under
  `app/PolydockEngine/Helpers/` if that matches the deploy-helper convention there —
  check and match).
- A `PollDeploymentRunJob` (queued) that polls one run to completion.
- Refactor the existing Trigger/Poll commands to delegate to the service so there is
  a single code path (keep the command signatures working).

**Out of scope:** scheduling/cadence (003), any UI (004), the formal UPGRADE
state machine.

**Files to touch:**
- `app/Services/PolydockDeploymentService.php` (new)
- `app/Jobs/.../PollDeploymentRunJob.php` (new; place beside other queued jobs)
- `app/Console/Commands/TriggerLagoonDeployOnAppInstances.php` (refactor to service)
- `app/Console/Commands/PollDeploymentStatusCommand.php` (refactor to service)
- reuse `app/Services/LagoonClientService.php` for the authenticated client

## Behaviour

### `redeploy(Collection $instances, string $triggerSource, ?User $by = null): PolydockDeploymentRun`

1. **Filter to eligible + not-in-flight.** Only instances in
   `RUNNING_HEALTHY_UNCLAIMED` / `RUNNING_HEALTHY_CLAIMED`. Skip any instance that
   already has an incomplete `deploymentRun` (idempotency — never double-fire).
   Log/return what was skipped and why.
2. **Group by Lagoon target.** Build `['project' => <lagoon project name>, 'name' =>
   <deploy branch>]` tuples. Instances of the **same store app** share a gitUrl/branch
   but are distinct Lagoon projects, so each instance is its own tuple — grouping is
   for the *run record* and for choosing single vs bulk, not for collapsing tuples.
3. **Trigger.** If >1 tuple, call `bulkDeployEnvironments($tuples, name: <run uuid>)`
   and store the returned `bulkId` on the run; if exactly 1, `deployProjectEnvironmentByName`.
   Set run `status = running`, `started_at`, `total_count`. Set each instance's
   `deployment_run_id` and `last_deploy_triggered_at`.
4. **Return** the persisted run. Dispatch `PollDeploymentRunJob` for it.

### `PollDeploymentRunJob`

1. Load the run; if terminal, return.
2. For a bulk run: `getDeploymentsByBulkId($bulkId)`; for a single: use
   `getProjectDeploymentByProjectIdDeploymentName` / `getProjectEnvironmentDeployments`.
3. Map each returned deployment back to its instance (by Lagoon project + environment
   name) and write `last_deployment_name`, `last_deployment_status`, and — when the
   Lagoon status is complete — `last_deployed_at` (Lagoon `completed`).
4. Update the run's `success_count` / `failed_count`; set `status` to `completed` /
   `partial_failed` / `failed` when all deployments are terminal, else re-queue with
   backoff (mirror the purge poll: `last_polled_at`, `poll_attempts`, a
   `deploy_poll_interval_minutes` config, and a `deploy_max_poll_attempts` cap).
5. **A failed build must NOT change instance status.** Record it on the instance's
   `last_deployment_status` and the run counts only. Lagoon's rolling deploy keeps
   the old pod serving, so the instance is still healthy/running.

Add config keys under `config/polydock.php`: `deploy.max_per_run`,
`deploy.poll_interval_minutes`, `deploy.max_poll_attempts`, `deploy.bulk_chunk_size`
(max tuples per `bulkDeployEnvironments` call — see 003).

## Steps

1. Write `PolydockDeploymentService::redeploy(...)`. **Verify:** unit-test the
   filter/skip/grouping with a fake Lagoon client (see `tests/Doubles`).
2. Write `PollDeploymentRunJob`. **Verify:** feature test with a fake client
   returning building→complete transitions across two polls updates the instance
   and run correctly.
3. Refactor `TriggerLagoonDeployOnAppInstances` to build a collection and call the
   service. **Verify:** the command still runs and now creates a `deployment_run`
   row. Keep the existing table output.
4. Refactor `PollDeploymentStatusCommand` / reconcile `BulkDeployStatus` to read
   from the run. **Verify:** running it against a seeded run reports status.
5. Confirm idempotency: calling `redeploy` twice for the same instance while a run
   is in-flight fires only once. **Verify:** test asserts one run, one Lagoon call.

## Test plan

`tests/Feature/Deployment/RedeployServiceTest.php` +
`tests/Feature/Deployment/PollDeploymentRunJobTest.php`, using a
`tests/Doubles` fake Lagoon client:
- Ineligible states (not RUNNING_HEALTHY_*) are skipped.
- In-flight instances are skipped (no double deploy).
- Bulk path used for >1 instance, single path for 1; `bulkId` persisted.
- Poll maps deployments back to instances by project+env and fills cached fields.
- Failed Lagoon build → instance status untouched, run `failed_count` incremented,
  run ends `partial_failed`/`failed`.
- Poll backoff caps at `deploy.max_poll_attempts` and marks the run failed rather
  than looping forever.

## Done criteria

- [ ] One service is the single deploy code path; commands delegate to it.
- [ ] A redeploy always produces a `PolydockDeploymentRun` with a `bulkId` (bulk) or
      single-deploy marker, and per-instance cached fields get populated by polling.
- [ ] Idempotent: no double-deploy for in-flight instances.
- [ ] Failed builds never mutate instance lifecycle status.
- [ ] All new tests pass; `pint --test` clean.

## STOP conditions

- If mapping bulk deployments back to instances is ambiguous (e.g. Lagoon returns
  deployments without a resolvable project+environment), stop and report the shape
  of the response — don't guess a mapping that could attribute a build to the wrong
  instance.
- If `bulkDeployEnvironments` does not return a usable `bulkId` in this environment,
  stop and report; the poll design depends on it.

## Maintenance notes

- The service is the seam every caller uses; keep Lagoon specifics inside it so
  003/004 stay Lagoon-agnostic.
- If Lagoon build-name/status field names differ from what the exploration found,
  adjust the mapping in one place (the poll job).
