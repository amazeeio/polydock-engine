# 005 — Deployment windows / blackout hours (FUTURE / DEFERRED)

- **Priority:** P3 (future — do not build with 001–004)
- **Effort:** M
- **Risk:** Low-Medium (timezone correctness is the main trap)
- **Dependencies:** 003 (cadence dispatch must exist first)
- **Category:** feature / scheduler policy
- **Planned at:** 2026-07-01
- **Status:** DEFERRED — captured now so plan 003 leaves a clean insertion point.

## Why this matters (future)

Some groups — especially large clients — will want their apps redeployed only at
controlled times: outside business hours, on a specific day, or never within a
blackout window. Cadence (003) decides *whether an app is due*; a deployment window
decides *whether "now" is an acceptable time to actually fire the due deploy*. The
two compose: a due instance whose window is currently closed simply waits for a
tick that lands inside its window.

This is explicitly **not** part of the initial rollout feature. It is documented so
that (a) 003 is built to accommodate it, and (b) it can be picked up as a standalone
unit later.

## Design sketch

### Where the policy lives

- Primary attachment: **`UserGroup`** (a client/tenant), since "our client only
  wants deploys at night" is a per-tenant concept. Optionally also a store-app-level
  or global default, resolved most-specific-first (instance's group → store app →
  global).
- Model options:
  - **Low-effort v1:** a JSON `deployment_window` config column on `user_groups`
    holding `{ timezone, allowed_days: [1..7], allowed_hours: {start, end} }` (or an
    inverse `blackout` shape). Good enough for a single window per group.
  - **Fuller:** a `polydock_deployment_windows` table (many windows per group,
    each with day/time-range/timezone) if clients need multiple disjoint windows.
  Decide based on whether "multiple windows per client" is a real requirement.

### The critical detail: timezone

"Outside work hours" is **local to the client**. Every window MUST carry an explicit
timezone; evaluate "is now in-window" by converting the current time into the
window's timezone. Storing only UTC hours will be wrong for anyone not in UTC.
Beware the codebase's restriction on ambient time helpers — use an injectable clock.

### Integration with 003

At the insertion point plan 003 left in its fire step:

```
foreach (dueInstance) {
    if (! deploymentWindowAllowsNow($instance)) {
        continue;   // stays due; retried on a later in-window tick
    }
    // ... proceed to group + redeploy + advance next_redeploy_at
}
```

Because due instances that miss their window are left with `next_redeploy_at`
unchanged, they are automatically retried every tick until the window opens — no
extra queue or state needed. Add a guard so an instance that has been "due but
blocked" for an unusually long time (e.g. a misconfigured window that never opens)
is surfaced/logged rather than silently never deploying.

### Manual vs scheduled

- **Manual** admin bulk redeploys (004) should **bypass** windows by default (an
  operator explicitly chose to deploy now) — but consider a "respect window" toggle
  on the bulk action for cases where an operator wants to *queue for the window*.
- **Scheduled/beta** cadence deploys **respect** windows. This split is a decision
  to confirm when the feature is picked up.

## Open questions to resolve when building

- One window per group, or many? (drives JSON-vs-table.)
- Does a window also gate **beta** cadence, or only the default cadence?
- What timezone source — stored per window, or derived from the group/client
  profile if one exists?
- Behaviour when a window is so narrow the per-run cap can't drain the backlog
  within it — do we raise the cap during the window, or just carry over?

## Done criteria (when eventually built)

- [ ] Window policy attached at group level (with resolution order if defaults added).
- [ ] "In-window now" evaluated in the window's timezone via an injectable clock.
- [ ] Scheduled cadence respects windows; blocked-but-due instances retry cleanly.
- [ ] Manual admin deploys bypass (or optionally respect) windows per decision above.
- [ ] Long-blocked instances are surfaced, not silently starved.
- [ ] Tests cover: in/out of window across timezones, day-of-week gating, blocked
      instance retried next tick, misconfigured never-open window is flagged.

## Notes

- Keep this orthogonal to cadence: cadence answers "due?", windows answer "now OK?".
  Don't merge them into one field — they change independently and belong to
  different owners (store-app cadence vs. client window policy).
