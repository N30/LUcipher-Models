# Phase 3 — Report

**Done:** 2026-10-18 · **Branch:** `claude/lumodels-production-01cqya` · Plan: `phase-3-safe-by-default.md`.

## What landed, in commit order

| Commit | What |
|---|---|
| `22952f6` fix | Web views resolve for plain Eloquent models (`viewFile()` was LuModel-only). |
| `0c0809a` feat | **Exposure per transport.** `config('lu::models.models')` or `public $lu_transports` on the model; union; `expose_all` for development. An unexposed model is a 404 like a missing one; the Navlist lists exposed models only. **Policy required**: a signed-in user on a model without a registered policy is denied with the policy name in the message; public opt-ins per model and action stay for guests; the super-admin bypass is `lu::models.super_admin_method` (null turns it off). |
| `4417172` feat | **Hidden never reads out; guarded keys rejected; `$lu_fields`.** One resolver (`Support\FieldVisibility`) for the service, the blades and the API. A client key the model does not allow written fails validation with "The {key} field cannot be written." instead of being dropped; `update()` validates like `store()`. The API answers 422 / 404 / 200 JSON through `runAction()`/`toResponse()`; rest carries validation errors back. |
| `c7aee09` fix | Model discovery reads `lu::models.models_path` (tests no longer move `app_path()`; see "CI taught us" below). |
| `b025f64` feat | **Rate limits**: named limiters per transport and for writes (`lu::models.rate_limits`, defaults 120/120/60/120 per minute, 20 writes per minute, null = off), applied with `throttle` middleware. |
| `4d0b88d` feat | **Audit log**: one `lu_models_audit` row per create/update/delete with model, key, action, transport, user, IP and the changed attributes (before/after on update; never `$hidden`); `store_values` and `enabled` switches; a missing table warns and never blocks. The package ships its migrations. |
| `d7158a6` fix | Controllers re-initialize per request (Laravel caches the instance on the Route); `show`/`edit` 404 for a missing record; `public_read_models` honoured for the `show` action (it read a non-existent key and crashed). |
| `9df3bcc` fix | `web/show` and `web/edit` left a `<x-slot>` open on every render; the layout component renders named slots; the edit forms no longer call the host app's `isAdmin()`. |
| `af857de` test | **CSRF** (419 on web and rest without a token, none on the API) and **IDOR** (owner vs another user on every action over web, api and rest; guest enumeration; `user_id` spoofing; missing record) per transport. |

## Numbers

| Measure | Phase 2 | Phase 3 |
|---|---|---|
| Tests | 62 / 285 assertions | 106 / 439 assertions |
| `src/` line coverage | 60.78 % | 74.53 % (floor raised 58 → 72) |
| Larastan level 5 | clean, 21 baselined | clean, 21 baselined |
| Default exposure | every model on an enabled transport | none; per model, per transport |
| Missing policy, signed-in user | falls through to the public allow-list | 403 naming the policy |
| Guarded key in a write | silently dropped | 422 naming the key |
| Writes recorded | no | yes, with before/after |

## CI taught us

- **Compiled Blade views are cached between local runs**, so a change that only shows on a cold
  compile (here: `app_path()` moved → Blade's component compiler could not detect the app namespace)
  passed locally and failed in CI. Local runs in this phase clear
  `vendor/orchestra/testbench-core/laravel/storage/framework/views` first.
- **Laravel caches a controller instance on its Route object.** Two requests to the same route inside
  one test reused a controller whose constructor-time state was stale. Production never sees it (one
  request per process) but Octane-style workers would; the controllers now re-initialize when a new
  request arrives.

## Decisions taken (defaults; shout if wrong)

- Public opt-ins (`public_{action}_models`, `public_pages`) still cover writes when the app lists them;
  the plan said "never covers writes", but a public `create` is a legitimate contact form and the
  defaults are empty. Documented, not removed.
- `expose_all` exists (default off) so the package's original "install and browse every model" story
  survives as a development mode, with policies still required.
- Rejecting a guarded key (422) rather than dropping it changes the contract for API clients that
  relied on silent dropping; there are no such clients yet.

## Carried forward

- The `/spa` transport (Livewire `Master`) is not covered by the security tests; phase 5 rewrites it
  and tests it then.
- `show` by plain id requires `allow_any_unencrypred_access` even for the owner; the index table links
  with plain ids, so the shipped UI cannot reach its own show/edit pages without that flag. Phase 5's
  API and admin UI decide the id scheme (policy-based, not obscurity-based).
- `index_columns` (LuModel) is still not applied to the index listing; phase 5's descriptor-driven UI
  does it.
