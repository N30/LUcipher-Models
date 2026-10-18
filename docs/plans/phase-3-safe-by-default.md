# Phase 3 — Safe by default

**Status:** planned 2026-10-18 · **Depends on:** phase 2 · **Study:** §2.4 (authorization lives in the
Action), §3 (IDOR, mass assignment), §7 Q6 (default: admin-only).

## What

1. **Nothing is exposed until a model says so.** `enable_*_routes` stays the global switch per
   transport; on top of it a model is reachable on transport *T* only when it is listed for *T* in
   `config('lu::models.models')` **or** declares `public $lu_transports = ['web', 'api']` itself. An
   unexposed model answers 404 on every transport, exactly like a model that does not exist. The
   `Navlist` component lists exposed models only.
2. **A policy is required.** `authorize()` resolves the policy with `Gate::getPolicyFor($model)`;
   a model without one is denied (403, message names the policy class the package looked for) even
   for an authenticated user. Public access to a read action stays an explicit opt-in per model
   (`public_pages` / `public_{action}_models`) and never covers writes. The super-admin bypass stays,
   behind `lu::models.super_admin_method` (default `is_super_admin`, `null` turns it off).
3. **`$fillable` and `$hidden` always win.** Writes already fail closed (3.0.1). Reads: every column
   list the package derives (`db_columns`, the generated forms, the index table, the JSON output) drops
   `$hidden` attributes and `hidden_columns`; a write that names a guarded key is rejected with a 422
   naming the key instead of being silently dropped, so a client learns the contract.
4. **Per-field visibility.** On the model: `public $lu_fields = ['title' => ['index', 'show',
   'create', 'edit'], 'secret_token' => []]`. A field absent from the map keeps today's behaviour; a
   field present is shown and writable only for the actions listed. One `FieldVisibility` resolver,
   used by the service, the blades and the API alike (phase 4's descriptor absorbs it).
5. **Rate limits per transport.** Named limiters `lu-models-{web,rest,api,spa}` registered by the
   provider from `lu::models.rate_limits` (defaults: api `60,1`, rest/web/spa `120,1`; writes
   `20,1`), applied as `throttle:` middleware on the registered routes.
6. **Audit log of writes.** Opt-in (`lu::models.audit.enabled`, default on once the table exists):
   an `AuditLogger` records every create/update/delete that goes through the package — model class
   and key, action, transport, user id, IP, the changed attribute names and, unless
   `audit.store_values` is off, the before/after values minus `$hidden`. Table `lu_models_audit` via
   a publishable migration; one row per write, written synchronously in the same request.
7. **Security tests on every transport** (web, rest, api; spa through Livewire's test harness):
   - mass assignment: a guarded key in the payload never lands, and the response says why;
   - IDOR: user A cannot show, edit, update or delete user B's record through the policy; a plain
     numeric id on `show` needs authentication unless the model allows it; the encrypted-id path
     works; an unexposed model is 404;
   - CSRF: `/web` and `/rest` POST/PUT/DELETE without a token are 419; `/api` has no CSRF but
     answers 401 without a token;
   - missing policy → 403 on every action, for a logged-in user;
   - rate limit: the 61st API call in a minute is 429.

## Why

The package's thesis is to expose every model over HTTP. Today a model is exposed the moment a
transport is enabled, authorization falls through to a public allow-list when no policy exists, and
nothing records who changed what. "Safe by default" means a fresh install with every transport on
still exposes nothing, and each exposure is a decision written in the model or the config, with a
log behind it.

## How verified

- New `SecurityTest` classes per transport, each test red before its change and green after.
- The existing 62 tests keep passing; `WebTransportTest` fixtures opt the `Post` model in.
- `composer analyse` stays clean; the coverage floor is raised to the measured value.

## Decisions taken without asking (defaults; shout if wrong)

- The exposure list is a union of config and the model's own declaration.
- Audit rows store values by default (minus `$hidden`); `audit.store_values=false` keeps only names.
- Rate limits are enforced by Laravel's `throttle` middleware, not a package-specific counter.
