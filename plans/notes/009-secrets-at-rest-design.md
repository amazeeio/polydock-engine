# Design note 009 — Encrypt user-supplied instance secrets at rest

Spike deliverable for `plans/009-encrypt-instance-secrets-at-rest-spike.md`.
Branch: `advisor/009-encrypt-instance-secrets-at-rest-spike`.

## Summary / recommendation

Adopt **Option 2 — encrypt the `secret` subtree in place** inside the existing
`storeKeyValue`/`getKeyValue` seam on `PolydockAppInstance`. The blast radius is
tiny and fully funnelled through that seam (2 writers, 2 readers, zero direct
`$this->data['secret']` access), so a transparent encrypt-on-write /
decrypt-on-read change needs **no call-site edits** and does not require a schema
change. A POC proving round-trip + ciphertext-at-rest is implemented on this
branch. No STOP condition was hit — the seam is clean and feasible.

Ship this alongside a **credential-rotation** ask for the ops team: encryption
does not un-leak secrets already stored in plaintext.

## Step 1 — Blast radius (all secret read/write sites)

The `secret` key is a JSON map of credentials (LLM key/url, backend token,
team id, vector-DB host/port/user/pass/name), optionally nested under `ai`/
`vector`. Every access goes through the model seam — verified by grep.

**Writers — `storeKeyValue('secret', ...)`** (2):

| File | Line | Notes |
|---|---|---|
| `app/Http/Controllers/Api/AuthenticatedApiController.php` | 573 | authenticated `createInstance` API; also validated at 461 (`secret` = `nullable\|array`) and merged from `config.secret` around 580 |
| `app/Polydock/Apps/AmazeeClaw/Traits/UsesManualAmazeeAiCredentials.php` | 65 | `extractAndStoreAiCredentialsFromHookData()` merges hook data into the secret map |

**Readers — `getKeyValue('secret')`** (2):

| File | Line | Notes |
|---|---|---|
| `app/Polydock/Apps/AmazeeClaw/Traits/UsesManualAmazeeAiCredentials.php` | 25 | read-merge-write of the secret map |
| `app/Polydock/Apps/AmazeeClaw/Traits/UsesManualAmazeeAiCredentials.php` | 108 | `provisionAndInjectManualAmazeeAiCredentials()` maps secret paths → Lagoon env vars |

**Direct `$this->data['secret']` / `data_get('secret')` access:** none found.
(`AppServiceProvider.php:76-77` `$config['secret']` is unrelated — AWS DynamoDB
credentials, not instance data.)

**Read-site count: 2.** Comfortably within a transparent seam — no broader
refactor required.

### Consumers downstream of the read

The secret is only ever turned into `AMAZEEAI_*` Lagoon **project env vars** and
injected via `addOrUpdateLagoonProjectVariable(...)` (Lagoon API). Values are
never inlined into shell commands (the claim script writes them to an
owner-only `/tmp` file via stdin, then deletes it —
`UsesManualAmazeeAiCredentials.php:237-249`).

### Logging / serialization safety (already in place)

- `PolydockAppInstance::$sensitiveDataKeys` includes `secret` (line ~142) and
  `App\Support\SensitiveDataRedactor` redacts `secret` / `*secret*` / `*pass*` /
  `*token*` / `*key*` — so log context is already scrubbed.
- The model has **no** `$appends`/`$hidden`/custom `toArray()` re-exposing
  `data`; the public register-status API returns curated fields, not the raw
  `data` blob. With Option 2 the stored value is ciphertext anyway.

## Step 2 — Options compared

### Option 1 — Dedicated `encrypted:array` column

Move secrets to a new `secrets` column: `protected $casts = ['secrets' => 'encrypted:array']`.

- Pros: cleanest separation; Laravel handles crypto transparently; `data`
  becomes pure non-secret config.
- Cons: schema migration + **data backfill** of existing rows; must update both
  writers and both readers (they use the `data` seam today); larger diff and
  review surface for what is currently a 4-site concern.

### Option 2 — Encrypt-in-place within `data` (RECOMMENDED)

Encrypt only the `secret` subtree inside `storeKeyValue`/`getKeyValue`.

- Pros: **zero call-site changes** (seam already special-cases keys, e.g. the
  health-webhook URL); no schema change; smallest surface; trivially reversible;
  legacy plaintext still readable during transition (prefix guard).
- Cons: `data` column mixes plaintext (config) and ciphertext (secret) — a
  cosmetic wart, mitigated by the `enc:v1:` prefix making intent explicit.

### Option 3 — External secret store / KMS (Vault, AWS KMS/Secrets Manager)

- Pros: strongest — separate trust boundary, per-secret access, audit,
  server-side rotation; secret material never sits in the app DB.
- Cons: highest effort and ops cost (provisioning, IAM, network, availability
  coupling on the deploy path); out of proportion for this spike.

**Chosen: Option 2** — best effort-to-protection ratio given the tiny, clean
seam. Option 1 is the natural follow-up if non-secret `data` and secrets should
be physically separated later; Option 3 if a compliance requirement demands a
KMS trust boundary.

## Step 3 — POC (implemented on this branch)

In `app/Models/PolydockAppInstance.php`:

- `const ENCRYPTED_KEYS = ['secret']` — the set of keys treated as secret.
- `storeKeyValue()` runs matching keys through `encryptSecretValue()` before
  `data_set`; `getKeyValue()` runs them through `decryptSecretValue()` before
  returning. All other keys and the existing webhook-URL logic are untouched.
- `encryptSecretValue()`: `json_encode` → `Crypt::encryptString(...)` → prefix
  with `enc:v1:`. Empty/`null`/`[]` stored as-is; already-prefixed input is
  returned unchanged (**idempotent, no double-encrypt**).
- `decryptSecretValue()`: only strings starting with `enc:v1:` are decrypted;
  anything else (legacy plaintext, empty default) passes through untouched — the
  **detect-already-encrypted guard** that also makes backfill safe.

Crypto: Laravel `Crypt` facade, keyed by `APP_KEY` (AES-256-CBC + HMAC
authentication). The `enc:v1:` prefix leaves room to version the scheme.

**Test:** `tests/Unit/Models/PolydockAppInstanceSecretEncryptionTest.php`

1. round-trip: `storeKeyValue('secret', $arr)` → `getKeyValue('secret')` equals
   `$arr`, including after a fresh DB reload;
2. ciphertext-at-rest: `getRawOriginal('data')` contains neither `sk-...` nor
   the DB password, and `data.secret` is a `enc:v1:` string;
3. idempotency: re-storing the decrypted value does not stack the prefix;
4. legacy plaintext under `data.secret` is still readable.

All 4 pass; full suite stays green.

## Step 4 — Migration strategy (design only — NOT run)

Backfill existing plaintext rows via a one-off idempotent, resumable command,
e.g. `php artisan polydock:backfill-instance-secrets`:

1. Iterate `PolydockAppInstance` in `chunkById(...)` batches (resumable).
2. For each row, read the **raw** `data.secret` (via `getRawOriginal`, bypassing
   the accessor). Skip if absent/empty **or** if it already starts with
   `enc:v1:` (the detect-already-encrypted guard) — makes re-runs safe.
3. For remaining plaintext values, encrypt with the same
   `encryptSecretValue()` logic and write back to `data.secret`, saving only
   that key.
4. Log a per-row `[skipped-empty | skipped-encrypted | encrypted]` outcome and a
   final tally; support `--dry-run`.
5. Because `getKeyValue('secret')` already passes plaintext through untouched,
   the app keeps working **during** the backfill — no downtime, no lock-step
   deploy.

Rollback: reverse command decrypts prefixed values back to plaintext (only
needed if the approach is abandoned). Because reads tolerate both forms, a
partial backfill is a safe steady state.

## Rotation & key-management notes (surface before rollout)

- **APP_KEY dependency.** `Crypt` derives from `APP_KEY`. Confirm `APP_KEY` is
  set, stable, and identical across all app + queue-worker environments that
  read/write instances (workers run the deploy pipeline that reads the secret).
  If `APP_KEY` differs or is rotated without re-encrypting, decryption throws
  `DecryptException`. **STOP-condition check:** APP_KEY handling per environment
  must be confirmed by ops before production rollout — call it out in the PR.
- **Key rotation** requires a re-encrypt pass (decrypt-with-old →
  encrypt-with-new); Laravel's `APP_PREVIOUS_KEYS` can bridge reads during a
  rotation window. The `enc:v1:` prefix lets a future scheme coexist.
- **Encryption ≠ access control.** This protects data at rest (stolen DB /
  backup); it does not gate who can call the API or read via the app. Anyone
  with `APP_KEY` + DB access can still decrypt.
- **Rotate already-exposed credentials.** Any secret stored in plaintext before
  this change is considered burned — encryption cannot un-leak it. Pair rollout
  with ops-driven rotation of those credentials (out of scope here).
- **Reviewer checklist** for the eventual production PR: no plaintext secret in
  logs/activity-log/debug paths (redactor already covers `secret`), and `secret`
  stays out of any `toArray()` / API serialization.
