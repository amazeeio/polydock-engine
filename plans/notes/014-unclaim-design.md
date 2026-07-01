# Design note 014: an "unclaim" / trial-reset lifecycle transition

Status: **DESIGN-ONLY**. This note enumerates what a claim sets up, proposes a
reverse ("unclaim") transition, and lists the open questions/risks the team must
resolve before any implementation plan. No application code is changed.

Grounded at commit baseline on branch `advisor/014-unclaim-trial-reset-design`.
All file/line references below were read from the working tree, not assumed.

---

## 0. Drift / plan-path note

The plan (`plans/014-unclaim-trial-reset-design.md`) and the root `CLAUDE.md`
describe two different repo layouts. This worktree uses the **consolidated**
layout the plan's own commands assume:

- Enum: `app/Polydock/Core/Enums/PolydockAppInstanceStatus.php`
- Claim job: `app/Jobs/ProcessPolydockAppInstanceJobs/Claim/ClaimJob.php`
- Claim trait: `app/Polydock/Apps/Generic/Traits/Claim/ClaimAppInstanceTrait.php`

`docs/backlog.md` referenced by the executor prompt does **not exist** in this
worktree (it is an untracked file only on `dev`). Roadmap check performed against
`docs/README.md` instead — see §5.4. No STOP condition triggered: the claim
side-effects are fully enumerable from code (§1), and no product decision against
reuse was found.

---

## 1. What a "claim" actually sets up (side-effects to reverse)

### 1.1 How claim runs

- `ProgressToNextStageJob` (`ProgressToNextStageJob.php:75-91`): on
  `POST_DEPLOY_COMPLETED`, if `$appInstance->remoteRegistration` exists **or**
  `getKeyValue('user-email')` is set, it transitions to
  `PENDING_POLYDOCK_CLAIM`; otherwise straight to `RUNNING_HEALTHY_UNCLAIMED`.
  **So "claimed" is gated on a user being attached, not on a separate flag.**
- The status-change dispatch table
  (`app/Listeners/ProcessPolydockAppInstanceStatusChange.php:211-216`) fires
  `ClaimJob` when the instance enters `PENDING_POLYDOCK_CLAIM`.
- `ClaimJob::handle()` (`ClaimJob.php:14-17`) calls
  `executeTransition(PENDING_POLYDOCK_CLAIM)`, which runs the Engine, which calls
  the `claimAppInstance()` trait.
- On `POLYDOCK_CLAIM_COMPLETED`, `ProgressToNextStageJob.php:92-101` sets
  `RUNNING_HEALTHY_CLAIMED`.

### 1.2 Direct side-effects of `claimAppInstance()`

From `ClaimAppInstanceTrait.php`:

1. **Status writes**: `POLYDOCK_CLAIM_RUNNING` (line 50) →
   `POLYDOCK_CLAIM_COMPLETED` (line 126) [or `POLYDOCK_CLAIM_FAILED`].
2. **Claim script execution on Lagoon** (lines 62-96): runs
   `lagoon-claim-script` in the project's service/container. This is
   **app-defined, side-effecting, and NOT known to be idempotent or reversible**
   — it can e.g. seed an admin account, rotate a password, mark the app "claimed"
   internally. There is no corresponding "unclaim script" concept in code.
3. **`claim-command-output` key-value** (lines 88 / 104): stores the claim URL
   (either script output or a resolved Lagoon route).
4. **App URL + one-time login URL** (lines 89 / 105):
   `setAppUrl($url, $oneTimeLoginUrl, 24)`. Per
   `PolydockAppInstance.php:1003-1011` + `:993-994`, this sets `app_url`,
   `app_one_time_login_url`, and `app_one_time_login_valid_until = now()+24h`.
5. **Lagoon project variable `POLYDOCK_CLAIMED_AT`** (line 117), set GLOBAL via
   `addOrUpdateLagoonProjectVariable`, always written even when no claim script.

### 1.3 Indirect / pre-existing state a claim relies on (set earlier, not by claim)

An unclaim design must be clear these are **NOT** created at claim time, so it
should NOT blindly wipe them:

- **User attachment (metadata)**: `user-email`, `user-first-name`,
  `user-last-name`, `company-name` key-values are written at *registration/
  allocation* time (`ProcessUserRemoteRegistration.php:298-308`), not at claim.
- **Owning group**: instance `belongsTo` a `UserGroup`
  (`PolydockAppInstance.php:880-883`); the instance is created via
  `userGroup->getNewAppInstanceForThisApp()`
  (`ProcessUserRemoteRegistration.php:289`). There is **no direct `user_id`** on
  the instance — ownership is the group + the `user-*` metadata + the
  `remoteRegistration` HasOne (`PolydockAppInstance.php:959`).
- **Generated app admin credentials**: `lagoon-generate-app-admin-username` /
  `-password` and the `POLYDOCK_GENERATED_APP_ADMIN_*` Lagoon vars are written at
  **PostCreate** (`Create/PostCreateAppInstanceTrait.php:82-83`), long before
  claim. Accessors: `getGeneratedAppAdminUsername/Password()`
  (`PolydockAppInstance.php:1028-1039`).
- **amazee.ai backend user + AI credentials**: created at PostCreate
  (`UsesAmazeeAiBackend.php:177-234`, `PostCreateAppInstanceTrait.php:133`).
- **Trial dates + `is_trial`**: set at allocation
  (`ProcessUserRemoteRegistration.php:290-291`).

### 1.4 Summary — the reversible surface of an unclaim

| Artifact | Set by | Should unclaim reverse it? |
|---|---|---|
| status → `RUNNING_HEALTHY_CLAIMED` | ProgressToNextStage | Yes → back to `RUNNING_HEALTHY_UNCLAIMED` (or pool) |
| `claim-command-output` KV | claim trait | Yes (stale claim URL) |
| `app_url` / one-time login URL + expiry | claim trait `setAppUrl` | Yes (invalidate; a re-claim regenerates) |
| Lagoon `POLYDOCK_CLAIMED_AT` var | claim trait | Yes (clear or set unclaimed marker) |
| Effects of `lagoon-claim-script` | claim trait | **Cannot be reversed generically** — see §5.1 (open question) |
| `user-*` / `company-name` metadata | registration | **Policy decision** — wipe for pool reuse, keep for hand-back (§5.2) |
| UserGroup / `remoteRegistration` link | allocation | **Policy decision** (§5.2) |
| generated admin creds + AI backend creds | PostCreate | Not a claim effect; wiping = data-hygiene decision (§5.2) |
| trial dates / `is_trial` | allocation | Reset only in the "trial-reset" variant (§2.3) |

---

## 2. Proposed transition design

### 2.1 New statuses (enum additions)

Mirror the existing claim four-state family, in `PolydockAppInstanceStatus`:

```
PENDING_POLYDOCK_UNCLAIM   = 'pending-polydock-unclaim'
POLYDOCK_UNCLAIM_RUNNING   = 'polydock-unclaim-running'
POLYDOCK_UNCLAIM_COMPLETED = 'polydock-unclaim-completed'
POLYDOCK_UNCLAIM_FAILED    = 'polydock-unclaim-failed'
```

Plus the enum's derived maps must be updated in lockstep (label, badge colour,
icon — the enum defines these per case around lines 160/232/304), and the model's
`$failedStatuses` (`PolydockAppInstance.php:219-234`) gets
`POLYDOCK_UNCLAIM_FAILED`. **Do NOT** add `POLYDOCK_UNCLAIM_COMPLETED` to
`$completedStatuses` blindly — see §3, ordinal handling.

### 2.2 Job + dispatch + trait

- **`app/Jobs/ProcessPolydockAppInstanceJobs/Unclaim/UnclaimJob.php`**: mirrors
  `ClaimJob`, `handle()` → `executeTransition(PENDING_POLYDOCK_UNCLAIM)`.
- **Dispatch entry** in `ProcessPolydockAppInstanceStatusChange` for
  `PENDING_POLYDOCK_UNCLAIM → UnclaimJob::dispatch(...)`, mirroring lines 211-216.
- **`UnclaimAppInstanceTrait::unclaimAppInstance()`** in
  `app/Polydock/Apps/Generic/Traits/Claim/` (or a new `Unclaim/` dir). Steps,
  reversing §1.2:
  1. Validate status is `PENDING_POLYDOCK_UNCLAIM`, configure/verify Lagoon
     client (reuse `validateAppInstanceStatusIsExpectedAndConfigureLagoon...`).
  2. Set `POLYDOCK_UNCLAIM_RUNNING`.
  3. If an **optional** `lagoon-unclaim-script` KV is present, run it (symmetric
     to claim). If absent, do nothing script-side — the generic adapter cannot
     invent a reversal for an app-specific claim script (§5.1).
  4. Clear/rotate the one-time login URL and `app_url` (or leave `app_url`, only
     expire the OTL — decision in §5.3), clear `claim-command-output`.
  5. Set Lagoon var `POLYDOCK_CLAIMED_AT` to empty / write
     `POLYDOCK_UNCLAIMED_AT` marker.
  6. Apply the chosen data-hygiene policy (§5.2) — wipe or keep `user-*`.
  7. Set `POLYDOCK_UNCLAIM_COMPLETED`.

### 2.3 Two target variants (must be decided, §5.2)

- **Variant A — hand-back / reset in place**: `POLYDOCK_UNCLAIM_COMPLETED →
  RUNNING_HEALTHY_UNCLAIMED`, keep UserGroup + metadata. Re-claimable by the same
  or a new user. Lightest.
- **Variant B — return to pool (trial reset)**: additionally detach UserGroup /
  `remoteRegistration`, wipe `user-*`, reset `is_trial`/trial dates, rotate
  credentials, so the instance rejoins the pre-warm/unallocated pool
  (`EnsureUnallocatedAppInstancesJob`). Heavier; strongest hygiene requirement.
  Note the pool machinery keys off allocation state, so B needs to make the
  instance genuinely look unallocated — verify against
  `EnsureUnallocatedAppInstancesJob` before building.

The transition wiring lives in `ProgressToNextStageJob` (add a
`case POLYDOCK_UNCLAIM_COMPLETED:`), the same place all other stage transitions
live.

---

## 3. CRITICAL: reverse transition breaks the forward-only ordinal model

`BaseJob.php:139-247` implements a **stale-job skip** that assumes **monotonic
forward progression**:

- `lifecycleStageOrdinal()` (`BaseJob.php:163-235`) assigns each stage a strictly
  increasing ordinal. Claim = **70**; the running states
  (`RUNNING_HEALTHY_*`) = **80**.
- `isKnownStatusProgression()` (`:237-247`) returns true only when
  `currentOrdinal > expectedOrdinal`.
- `shouldSkipBecauseStatusAdvanced()` (`:113-137`), called from
  `executeTransition()` (`:306-311`), **silently skips** a job whose expected
  status has a *lower* ordinal than the instance's current status.

Consequences for unclaim:

1. An unclaim moves an instance **backwards** in ordinal space (from 80 ↔ 70, or
   into a new stage that sits between them). Any ordinal chosen for
   `POLYDOCK_UNCLAIM_*` will violate the "each later stage has a higher ordinal"
   invariant, because unclaim is legitimately reachable *from* a higher stage
   (`RUNNING_HEALTHY_CLAIMED`, ordinal 80).
2. Concretely: if a queued `UnclaimJob` (expected `PENDING_POLYDOCK_UNCLAIM`)
   sits behind, and the instance is still at `RUNNING_HEALTHY_CLAIMED` (80), then
   with any unclaim ordinal < 80 the job would be **wrongly skipped as stale**.
   If unclaim ordinal > 80, then a *later* re-claim / progression job could in
   turn be mis-skipped.
3. Any status **not present** in the `match` returns `null`, and
   `isKnownStatusProgression` returns `false` for null → the skip never fires.
   That is a viable escape hatch: **deliberately leave the `POLYDOCK_UNCLAIM_*`
   statuses out of `lifecycleStageOrdinal()`** so the stale-skip logic never
   applies to them. But then unclaim loses the stale-job protection entirely and
   must rely solely on `WithoutOverlapping` + the strict status equality check in
   `executeTransition` (`:306`).

**Recommendation for the implementer**: do NOT try to slot unclaim into the
linear ordinal scale. Model the lifecycle honestly as a graph (claim ↔ unclaim is
a cycle), and either (a) exclude unclaim statuses from the ordinal map and lean
on the exact-status guard, or (b) generalise the stale-detection to an explicit
allowed-transitions set rather than a monotonic ordinal. Option (b) is the
cleaner long-term fix but is a broader change to `BaseJob` and must be covered by
characterization tests first. This is the single biggest correctness risk in the
whole feature and must be called out in the implementation plan.

---

## 4. Interaction with trial state and removal

- **Removal is currently the only exit** from a claimed/running instance
  (`Remove/*` jobs, ordinals 120-150). Unclaim is a *new second exit* that keeps
  the instance alive. The implementation must ensure an in-flight removal and an
  unclaim can't race (both are guarded by `WithoutOverlapping` per instance, but
  the status guards must be airtight).
- **Trial extension** (`ExtendAppInstanceTrial`, referenced by the plan) mutates
  trial dates only; unclaim Variant B overlaps with it conceptually (both touch
  trial lifecycle) — reuse `calculateAndSetTrialDates()`
  (`PolydockAppInstance.php:1071`) rather than duplicating date math.

---

## 5. Open questions & risks

### 5.1 The `lagoon-claim-script` is not generically reversible (HARD BLOCKER)

The claim's most important side-effect — running an arbitrary app-defined
`lagoon-claim-script` inside the environment — has **no inverse in code**. If a
claim seeds/rotates an admin user or writes app state, the generic adapter cannot
undo it. Options: (a) require each app to supply a `lagoon-unclaim-script` (opt-in
apps only); (b) restrict unclaim to a full teardown+redeploy of the environment
(effectively "reset" not "unclaim"); (c) accept that unclaim only resets Polydock
metadata and the in-app claim state persists (weak — a re-claim may fail if the
script isn't idempotent). **This must be decided before building.**

### 5.2 Data hygiene: wipe credentials/user data before returning to pool?

If an instance goes back to the pool (Variant B) still carrying the previous
user's `user-email`, generated admin password, and AI backend credentials, that
is a data-leak to the next claimant. Pool reuse therefore **requires** rotating
generated admin creds (they were set at PostCreate, not claim), revoking/rotating
the amazee.ai backend user/creds, and wiping `user-*`/`company-name`. This is
significant work beyond "reverse the claim" and argues that Variant B may be
better served by remove+recreate than by unclaim. Variant A (hand-back, same
owner) sidesteps most of this.

### 5.3 What to do with `app_url` / one-time login URL

Claim sets a 24h one-time login URL. Unclaim should at minimum expire the OTL
(set `app_one_time_login_valid_until` to the past / null). Whether to also clear
`app_url` depends on whether the environment stays up (Variant A: probably keep;
Variant B: clear).

### 5.4 Billing / quota

No billing/quota code was located in the claim path. Releasing a claimed instance
back to the pool has implications for trial accounting (does the released user get
a fresh trial? does the instance's cost keep accruing while pooled?). Out of scope
per the plan, but flagged: any implementation must confirm with product how a
released/re-pooled instance is counted.

### 5.5 Idempotency

`claimAppInstance` is only partially idempotent (it always re-writes
`POLYDOCK_CLAIMED_AT`; the claim script may not be). An `UnclaimJob` can be
retried by the queue, so `unclaimAppInstance` must be safe to run twice —
prefer unconditional "set to unclaimed" writes and tolerate already-cleared
values.

### 5.6 Admin-only vs user-initiated

The plan asks this explicitly. Given §5.1/§5.2 risks, the safe default is
**admin-only** (a Filament action on the instance) for the first version. A
user-facing "release my trial" endpoint should wait until claim-script
reversibility and data hygiene are resolved. No existing public API route
(`routes/api.php`) covers unclaim today.

### 5.7 Product-direction check (STOP condition)

`docs/README.md:34` lists claiming as a supported forward feature; **nothing in
`docs/` or `plans/` was found that decides *against* reuse/unclaim**. So no
contradiction — the design may proceed. (`docs/backlog.md` named in the prompt
does not exist in this worktree; if it exists on `dev`, re-check it before
implementing.)

---

## 6. Recommendation

1. Ship **Variant A (admin-only hand-back)** first: new four-state
   `POLYDOCK_UNCLAIM_*` family, `UnclaimJob`, `unclaimAppInstance` trait that
   clears claim metadata + OTL, requires an opt-in `lagoon-unclaim-script` for
   apps that need in-app reversal, and transitions back to
   `RUNNING_HEALTHY_UNCLAIMED`.
2. Treat the **`BaseJob` ordinal model (§3)** as a prerequisite design decision,
   not an afterthought — exclude unclaim statuses from the ordinal map for v1 and
   plan the graph-based generalisation as follow-up.
3. Defer **Variant B (pool reset)** until §5.1 and §5.2 are resolved; it may be
   cheaper to implement "recycle" as remove+recreate than as a true unclaim.
