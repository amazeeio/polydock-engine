# 004 — Admin UI: list columns, bulk action, scheduled view, permission

- **Priority:** P2
- **Effort:** M
- **Risk:** Low (read-mostly; one write action reuses the 002 service)
- **Dependencies:** 001, 002 (003 recommended first so the scheduled view has data)
- **Category:** feature / admin UI
- **Planned at:** 2026-07-01

## Why this matters

Admins need to see last/next/in-flight deploy state and trigger rollouts in bulk,
without opening the Lagoon UI per app. Because deploy state is now **cached** on the
instance (001/002), the list page can show it cheaply for thousands of rows —
unlike today's live-from-Lagoon fetch in the view page's `trigger_deploy` action.

Scope this deliberately: **do not rebuild Lagoon's build console.** Render lists and
the schedule view from *our* tables; only drill-in hits Lagoon live.

## Scope

**In scope:**
- New permission (super_admin only for now); optional dedicated role.
- `PolydockAppInstanceResource`: list columns (last deploy status/time, next
  redeploy), filters, and a **bulk "Redeploy selected"** action.
- `PolydockStoreAppResource`: cadence form fields (`redeploy_enabled`,
  `redeploy_interval_days`, `beta_redeploy_interval_days`) + a "Redeploy all
  instances of this app" action.
- `UserGroupResource` (if it exists): `is_beta` toggle.
- A **Scheduled Deployments** admin page (Filament resource or custom page) backed
  by `PolydockDeploymentRun` showing in-flight/recent runs with counts.

**Out of scope:** deploy mechanics (002), scheduler (003).

## Permissions

- Add a permission, e.g. `manage_polydock_deployments`, via the filament-shield /
  spatie setup already in use (`SuperAdminRoleSeeder` grants super_admin
  everything). Gate the bulk action, the redeploy-all action, and the Scheduled
  Deployments page behind `hasRole('super_admin')` OR the new permission — match the
  existing gate style in `PolydockAppInstanceResource` (`viewAny` etc.).
- A dedicated non-super-admin role is explicitly a *maybe later* — add the permission
  now so a role can be granted it without code changes, but don't create the role
  unless asked.

## Steps

1. **List columns** on `PolydockAppInstanceResource`: `last_deployment_status`
   (badge, colour by state), `last_deployed_at`, `next_redeploy_at` (toggleable).
   Eager-load `deploymentRun`/`storeApp` to avoid N+1 (see ../010). **Verify:**
   list page renders; no N+1 (check with query log / debugbar).
2. **Filters:** by `last_deployment_status`, "has in-flight deploy", "due for
   redeploy". **Verify:** filters narrow results correctly.
3. **Bulk action** "Redeploy selected": confirmation modal stating count + that it
   fires real Lagoon builds; on confirm, call
   `PolydockDeploymentService::redeploy($records, 'manual', auth()->user())`.
   Replace the current empty `bulkActions([])`. **Verify:** selecting instances and
   confirming creates a `deployment_run` and shows a success notification; gated by
   permission.
4. **Store-app form:** add the three cadence fields in a "Redeploy schedule" section
   (mirror the existing "Pre-warm Settings" section). Add a "Redeploy all instances"
   header action that calls the service for that app's eligible instances.
   **Verify:** saving persists; action fires a run.
5. **UserGroup:** add `is_beta` toggle to its resource/form. **Verify:** persists.
6. **Scheduled Deployments page:** a `PolydockDeploymentRunResource` (read-only-ish)
   listing runs with store app, trigger source, status badge, counts,
   started/completed, and a link out to Lagoon (build) for drill-in. **Verify:**
   page lists seeded runs; gated by permission.
7. Reconcile with the view page's existing `trigger_deploy` action — it can stay,
   but its "last deployment date" placeholder should read the cached
   `last_deployed_at` first and only fall back to a live Lagoon call. **Verify:**
   view page shows cached value without a Lagoon round-trip when present.

## Test plan

`tests/Feature/Deployment/DeploymentAdminTest.php` (Filament testing helpers):
- Bulk "Redeploy selected" is hidden for a user without the permission, visible for
  super_admin, and calls the service with `trigger_source = manual`.
- Store-app cadence fields save and load.
- `is_beta` toggle persists on a user group.
- Scheduled Deployments page lists runs and is permission-gated.
- List columns render cached deploy fields and don't trigger N+1 (assert query count).

## Done criteria

- [ ] `manage_polydock_deployments` permission exists and gates all write/admin
      surfaces; super_admin retains access.
- [ ] Instance list shows cached deploy state with no N+1; bulk redeploy works via
      the 002 service.
- [ ] Store-app cadence fields + redeploy-all action work.
- [ ] `is_beta` editable on user groups.
- [ ] Scheduled Deployments page renders runs, gated.
- [ ] Tests pass; `pint --test` clean.

## STOP conditions

- If no `UserGroupResource` exists in the admin panel, report — adding a full
  resource is out of scope; a minimal edit surface for `is_beta` may need its own
  small decision.
- If filament-shield auto-generates permissions from resources (so a hand-added
  permission would be overwritten on regeneration), stop and report the correct way
  to register `manage_polydock_deployments` in this project's shield setup.

## Maintenance notes

- Keep the Scheduled Deployments view sourced from `polydock_deployment_runs`, not
  live Lagoon queries — that's what makes it cheap at scale. Live Lagoon calls only
  on explicit drill-in.
- If ops later want a non-super-admin "deploy operator" role, it's now a seeder
  change granting `manage_polydock_deployments` — no code changes needed.
