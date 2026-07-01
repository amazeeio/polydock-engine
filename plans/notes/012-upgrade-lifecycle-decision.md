# Decision note 012 — Upgrade lifecycle: implement vs. guard

**Spike for:** `plans/012-upgrade-lifecycle-spike.md`
**Investigated at:** commit `b6f2ff09d195` (branch `advisor/012-upgrade-lifecycle-spike`)
**Drift check:** `git diff --stat b6f2ff09d195..HEAD -- app/Jobs/.../Upgrade app/Jobs/.../Health` = **clean** (no drift).

## TL;DR

- **Recommendation: no guard, no urgent code change.** The upgrade path is
  **unreachable in production today** — there is no admin action, console command,
  API endpoint, or state-machine transition that can move an instance into
  `PENDING_PRE_UPGRADE`. The only ways in are a raw DB edit or writing new code.
- The silent-stall bug the audit describes is **real** but **latent**: it can only
  bite once someone wires an entry point. It is not stranding live instances now.
- When the team does implement upgrade, it is **cheaper than a from-scratch build**:
  the trait layer is already scaffolded (status transitions mirror deploy). The
  missing pieces are (a) four one-line job bodies and (b) the actual Lagoon upgrade
  action inside two traits, plus polling.
- Recommend a **staged implementation follow-up** (outline below) rather than a
  guard, because there is nothing live to protect.

## Step 1 — Reachability (the central question)

An instance's status is only ever moved into `PENDING_PRE_UPGRADE` by code that
calls `setStatus(...PENDING_PRE_UPGRADE)` or an equivalent transition. I searched
Filament, Console, Http, routes, jobs, and the whole `app/` tree:

| Vector | Finding |
|---|---|
| **Filament row/bulk/header actions** | `PolydockAppInstanceResource` + its Pages expose: view, edit, create, export, `rerun_claim_hook`, `trigger_deploy`, `retry_failed_instance`, `extend_trial`, `delete_instance`, `force_full_delete`, `retry_purge`, `cancel_force_purge`. **No `trigger_upgrade` / "Stage Upgrade" action.** |
| **Filament edit form** | The `form()` schema has **no editable `status` field** (only `name` etc.). Status is display-only (`TextColumn`, `TextEntry`) and used as a filter. So the admin cannot pick `PENDING_PRE_UPGRADE` from the UI. |
| **Console commands / scheduler** | `grep` over `app/Console` + `routes/console.php` for `upgrade` = **no matches**. No command dispatches or sets the upgrade status. |
| **Public / authenticated API** | `routes/api.php` has register, regions, `instance/{uuid}/status`, and health callbacks. None set `PENDING_PRE_UPGRADE`. The `upgrade-*` strings in `AuthenticatedApiController` are only OpenAPI doc examples of possible statuses. |
| **State machine (`ProgressToNextStageJob`)** | Transitions `POST_DEPLOY_COMPLETED → RUNNING_HEALTHY_UNCLAIMED` and claim → `RUNNING_HEALTHY_CLAIMED`. **Nothing transitions any status *into* `PENDING_PRE_UPGRADE`.** The upgrade chain (`ProgressToNextStageJob.php:126-146`) only handles *onward* transitions once already inside the upgrade stages. |

The read-only references the audit grep surfaced are exactly that — read-only:
`UserGroupResource` counts `appInstancesStageUpgrade()`, `PolydockAppInstanceResource`
offers an "Upgrade Stage" *filter*, `PolydockStoreAppResource` has form fields for
configuring upgrade scripts (config only, does not run anything), and the
`AuthenticatedApiController` doc block lists upgrade statuses as examples.

**Conclusion: unreachable except by manual DB manipulation or new code.** No STOP
condition ("reachable in production UI/API AND stalls silently") is triggered.

## Step 2 — The silent-stall mechanism (why it *would* be a bug)

If an instance ever did reach `PENDING_PRE_UPGRADE`:

1. `ProcessPolydockAppInstanceStatusChange` listener dispatches `PreUpgradeJob`
   onto `polydock-app-instance-processing-upgrade` (listener lines 187-193).
2. `PreUpgradeJob::handle()` calls `polydockJobStart()`, logs
   `TODO: Implement PreUpgradeJob`, then `polydockJobDone()`. It **never calls
   `executeTransition(...)`**, so the Engine is never invoked and the status is
   never advanced.
3. The instance sits in `PENDING_PRE_UPGRADE` forever — no error, no failure, no
   completion. Nothing re-dispatches, so it silently stalls.

Contrast the working template `Deploy/PreDeployJob::handle()`, which is a single
line: `$this->executeTransition(PolydockAppInstanceStatus::PENDING_PRE_DEPLOY);`.
That call runs the Engine, which runs the trait, which advances the status.

The same stub shape applies to `UpgradeJob`, `PostUpgradeJob`, `PollUpgradeJob`,
and `Health/PollHealthJob` (all `Log::info('TODO: Implement ...')`).

## Step 3 — Implementation cost (cheaper than the audit implied)

The trait layer is **already scaffolded** under
`app/Polydock/Apps/Generic/Traits/Upgrade/`:

| Trait | State | Notes |
|---|---|---|
| `PreUpgradeAppInstanceTrait` | **Complete** | Validates `PENDING_PRE_UPGRADE`, sets `PRE_UPGRADE_RUNNING`, verifies Lagoon values, sets `PRE_UPGRADE_COMPLETED`. Nothing to do beyond validation — mirrors pre-deploy. |
| `UpgradeAppInstanceTrait` | **Scaffolded** | Full status flow (validate → `UPGRADE_RUNNING` → `UPGRADE_COMPLETED`) but the actual work is `warning('TODO: Implement upgrade logic')`. This is where the real Lagoon deployment/redeploy call must go. |
| `PostUpgradeAppInstanceTrait` | **Scaffolded** | Sets `POST_UPGRADE_COMPLETED` (`:65`); body is `TODO: Implement post-upgrade logic`. |
| `PollUpgradeProgressAppInstanceTrait` | **Stub** | `TODO: Implement upgrade progress logic`; ~near-empty, like `Health/PollHealthProgressAppInstanceTrait`. |

`Engine.php` **already routes** `PENDING_PRE_UPGRADE / PENDING_UPGRADE /
PENDING_POST_UPGRADE` to `preUpgradeAppInstance / upgradeAppInstance /
postUpgradeAppInstance` (lines 273-300), and the status-change listener already
dispatches the jobs. So the wiring exists on both ends; only the job bodies and
the trait *work* are missing.

**Effort estimate to mirror deploy:** the four job bodies are one-liners each (S).
The genuine work is `UpgradeAppInstanceTrait` (the real Lagoon upgrade call) plus
polling (`PollUpgradeJob` + `PollUpgradeProgressAppInstanceTrait`), modelled on
`DeployAppInstanceTrait` + `PollDeployProgressAppInstanceTrait` (the latter is
6.4KB — the substantive part). Net: **M**, dominated by (a) deciding what a Lagoon
in-place upgrade actually does and (b) polling, not by plumbing.

External dependency check: an in-place upgrade almost certainly reuses the same
Lagoon deploy/redeploy GraphQL calls the deploy stage already uses, so no *new*
Lagoon capability is obviously required — but confirming the exact upgrade
semantics (redeploy vs. new image tag vs. run a script) is a **product decision**
and should be pinned down before building `UpgradeAppInstanceTrait`.

## Recommendation

**Do not guard now.** A guard exists to protect live instances from stalling, and
there are none — the path cannot be entered without new code or a manual DB write.
Adding a guard (e.g. throwing in the stub jobs) would be dead code that a future
implementer has to remove, and it protects nothing today.

**Instead:**

1. **Document** the latent hazard (this note + keep `docs/ARCHITECTURE/README.md`
   honest that upgrade/health are stubs).
2. **Optional, low priority defensive tweak (not done here):** when the upgrade
   feature is scheduled but not yet finished, make the stub jobs `throw` instead of
   silently completing, so the *first* wiring mistake fails loudly (visible failed
   job) rather than stranding an instance. Only worth doing the moment someone
   starts building an entry point. Not warranted while the feature is untouched.

### Staged follow-up outline (when upgrade is prioritised)

- **Stage A — plumbing (S):** Replace the four stub job bodies with
  `executeTransition(PolydockAppInstanceStatus::PENDING_PRE_UPGRADE)` etc.,
  mirroring `Deploy/{PreDeployJob,DeployJob,PostDeployJob}`. With the traits as-is
  this already advances `PRE_UPGRADE` end-to-end and reaches
  `UPGRADE_RUNNING` (then stops at the `TODO` warning). Add a feature test asserting
  an instance in `PENDING_PRE_UPGRADE` advances rather than stalls.
- **Stage B — upgrade action (M, product input needed):** Implement the real work
  in `UpgradeAppInstanceTrait::upgradeAppInstance` (the Lagoon call) and
  `PostUpgradeAppInstanceTrait`, modelled on `DeployAppInstanceTrait`.
- **Stage C — polling (M):** Implement `PollUpgradeJob` +
  `PollUpgradeProgressAppInstanceTrait` modelled on `PollDeployProgressAppInstanceTrait`;
  `ProgressToNextStageJob` already expects polling to drive `POST_UPGRADE_COMPLETED`
  onward.
- **Stage D — entry point (S):** Add a Filament `trigger_upgrade` action (guarded
  to only `RUNNING_HEALTHY_*` instances) that sets `PENDING_PRE_UPGRADE`. Do this
  **last**, only after A-C are green, so the path is never reachable before it works.
- **Separate concern:** `Health/PollHealthJob` + `PollHealthProgressAppInstanceTrait`
  are likewise stubs — out of scope here, track separately.

## No code changed

Per the plan, a guard is warranted only if the path is reachable in production *and*
guarding is recommended. It is not reachable, so this spike delivers the decision
note only. `php artisan test` was left untouched (baseline 291 on `dev`).
