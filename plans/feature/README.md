# Feature: Scheduled & batched Lagoon redeploys ("upgrade rollouts")

Roll out redeploys to running Polydock app instances — manually in bulk, and
automatically on a per-app cadence — without overloading Lagoon, and surface the
last/next/in-flight deployment state in the admin panel.

## Design decisions (locked)

These were decided up front and constrain every plan below:

1. **Mechanism = redeploy-latest, tracked separately.** Rollouts trigger a plain
   Lagoon redeploy (`bulkDeployEnvironments` / `deployProjectEnvironmentByName`)
   and are tracked in **new tables**. Instances **stay in `RUNNING_HEALTHY_*`** —
   rollouts do **not** route through the formal `PRE_UPGRADE → UPGRADE →
   POST_UPGRADE` state machine (that stays stubbed; see "Relationship to existing
   plans"). Reason: bulk deploy returns a single `bulkId` for N environments and
   cannot drive N per-instance status transitions.
2. **No version/target concept (yet).** "Rolled out" means "redeployed since time
   T". We redeploy whatever the branch/image currently points at. No version
   columns, no "N of M on version X" reporting.
3. **Eligible instances = pre-warm/unallocated pool + claimed apps**
   (`RUNNING_HEALTHY_UNCLAIMED`, `RUNNING_HEALTHY_CLAIMED`). **Trials are
   excluded** from automatic cadence redeploys.
4. **Beta = a flag on `UserGroup` + a cadence override.** A beta group gets a
   shorter redeploy cadence than the store-app default. The "beta = different
   gitUrl" idea is explicitly out of scope for now; the instance selector must be
   written so it can slot in later.

## What already exists (reuse, do not rebuild)

- **Lagoon client** (`app/Polydock/Clients/...`, package `ft-lagoon-php`):
  `deployProjectEnvironmentByName()`, `bulkDeployEnvironments()` (→ `bulkId`),
  `getDeploymentsByBulkId()`, `getProjectEnvironmentDeployments()`.
- **Commands:** `TriggerLagoonDeployOnAppInstance` (single),
  `TriggerLagoonDeployOnAppInstances` (bulk), `PollDeploymentStatusCommand`,
  `BulkDeployStatus`.
- **Filament:** `PolydockAppInstanceResource` list/view; the view page's
  `trigger_deploy` action already fetches last-deploy info **live from Lagoon**
  (`getProjectEnvironmentDeployments`) — this is the thing to *cache* so it works
  on the list page for 1000s of rows.
- **Reusable patterns:** the purge command's per-run cap + backoff
  (`purge_max_per_run`, `purge_last_attempted_at`, `purge_poll_interval_minutes`),
  and the `next_poll_after` timestamp + `(status, next_poll_after)` index — mirror
  these for `next_redeploy_at`.
- **Libraries are inlined** into `app/Polydock/` (no external Composer packages,
  no tag-and-cascade release dance). Upgrade traits live at
  `app/Polydock/Apps/Generic/Traits/Upgrade/`.

## Relationship to existing plans

- **`../012-upgrade-lifecycle-spike.md`** asked "implement the stubbed UPGRADE
  jobs vs. guard them". This feature answers it for the *rollout* use case: we do
  **not** implement the UPGRADE state machine — we redeploy outside it. Leave plan
  012 as-is for now (out of scope for this feature); just don't route rollouts
  through the enum's upgrade states.
- **`../010-eager-load-store-apps-admin-list.md`** (N+1 on admin lists) — the new
  list columns in plan 004 here must not reintroduce N+1s; eager-load.

## Plans (execute in order)

| # | Title | Depends on |
|---|---|---|
| 001 | Deployment-tracking data model (migrations + models) | — |
| 002 | Redeploy service + poll (persist deploy state) | 001 |
| 003 | Scheduled cadence dispatch (cron, throttle, beta) | 001, 002 |
| 004 | Admin UI: list columns, bulk action, scheduled view, permission | 001, 002 |
| 005 | Deployment windows / blackout hours (**future / deferred**) | 003 |

001 is pure schema. 002 refactors the existing Trigger/Poll commands to write
through the new model. 003 and 004 both build on 002 and can be done in either
order, but 003 first is recommended (004's "scheduled view" reads what 003 writes).
005 is a **future** enhancement — not part of the initial build — but plan 003 is
written to leave a clean insertion point for it (a window gate before firing).

## Confirmed operating assumptions

- **Lagoon build concurrency (confirmed):** Lagoon enforces its own cluster-side
  build cap. Deploys we trigger simply queue and run automatically as capacity
  frees up — this is **not** a problem to engineer around. Our throttle
  (per-run cap + `bulk_chunk_size`) exists only to avoid one giant mutation and to
  pace *our* backlog, not to protect Lagoon. Size these for smooth draining, not
  for fear of overload.
- **Non-destructive redeploy (confirmed):** apps are built with persistent storage,
  so a redeploy cleanly rebuilds while keeping data; Lagoon does a rolling deploy
  (old pod serves until the new build succeeds). Redeploying **claimed** customer
  apps is therefore safe. A failed build leaves the running app untouched — the
  poll job (002) records the failure on the run/instance without changing instance
  lifecycle status.
