# Phase 1 — Report

**Done:** 2026-10-18 · **Branch:** `claude/lumodels-production-01cqya` (not merged to `master`; see
the open question on pull requests in the session report) · Plan: `phase-1-baseline.md`.

## What landed, in commit order

| Commit | What |
|---|---|
| `ab44b99` merge | `fix/3.0.1-security` reviewed and merged: method-scoped routes with a controller guard, fail-closed write safety, per-action string events, one Action override name, the `Verb` base class, the model-named Event override, the empty-index guard and the web redirect repair. 46 class-level tests came with it. |
| `1fdc8fc` style | Pint, laravel preset, whole tree. Formatting only. |
| `3e43f45` test | Testbench harness: `tests/TestCase.php` boots the provider on in-memory SQLite with a fixture app (`App\Models\Post`, `User`, `PostPolicy`, migrations, a layout). The class-level harness from the fix branch lives on as `UnitTestCase`. Dev dependencies for everything the shipped code needs to boot. |
| `83a466d` fix | Portable schema read (`Schema::getColumns`/`getIndexes` in the `SHOW FULL COLUMNS` shape); class components registered under `lu_models`; `index-filters` takes the controller's model. `WebTransportTest` keeps the study §3 bugs closed end to end. |
| `193b65e` fix | Larastan-surfaced defects: `can()` now passes model, data and transport to the policy; `viewFolder()` reads `$this`; `broadcastOn()` returns `[]`; declared properties on `IndexTable`, local service in `Master::render()`, no `return false` from a constructor. `OverrideLadderTest` proves the view-folder rung and the policy arguments. |
| `97e9412` ci | GitHub Actions: preflight, Pint, PHP 8.2/8.3/8.4 × Laravel 11/12 matrix (Testbench 9/10), pcov coverage with a floor, Larastan level 5 with a 21-entry baseline. |
| `9d1279d` chore | `version` dropped from `composer.json` (strict validate). |

## Before → after

| Measure | Before (`master` 19b1d21) | After (`9d1279d`) |
|---|---|---|
| Tests | 3 unit, no container | 57 (46 class-level + 11 feature), 267 assertions, green |
| `src/` line coverage | 0.53 % | 60.15 % (floor 58 %, ratchets up) |
| Pint | 32 of 32 files dirty | clean, enforced in CI |
| Static analysis | none | Larastan level 5, 0 new errors, 21 baselined |
| CI | lint-only | preflight + Pint on every push; matrix + Larastan gated on `LU_UNITE_TOKEN` until phase 2 |
| Security | GET deletes, update past `$fillable`, delete fatals | each closed and pinned by a test on `/web` |

## Review notes on `fix/3.0.1-security` (non-blocking, carried forward)

1. `store()` still reads `$data->model->id` directly where `update()`/`destroy()` use the new
   `recordOf()`; an app Action override returning the model breaks store only. Folds into the Result
   object (study §5.9, phase 4).
2. `Verb` still extends `Thunk\Verbs\Event`, and `AsAction`, Verbs, Livewire and Flux are still
   undeclared in `require`. Phase 2 declares what is needed; phase 4 decides what stays (🔴 Q2, Q3).
3. The class-level harness stubs framework pieces in `tests/bootstrap.php`; with Testbench the real
   ones load first and the stubs are inert. Remove them when the dependency decision lands.

## Verified how

- `composer test` → 57 tests green; `composer lint` clean; `composer analyse` → no errors against the
  baseline. All run on PHP 8.3.6 with Laravel 12.70 / Testbench 10.12 locally.
- Coverage measured with pcov built from source (sandbox has no coverage extension);
  `tools/ci/coverage-floor.php` exercised against the Clover output at a passing and a failing floor.
- CI: run 3 on this branch failed preflight on the `version` field (fixed in `9d1279d`); the run on
  the fix is the one to read. Matrix and Larastan report *skipped* until the token exists or phase 2
  lands.

## Carried into later phases

- Phase 2: provider stands alone (dry run done, see `phase-1-baseline.md` § Phase 2 dry run); drop the
  CI token gate; declare runtime dependencies.
- Phase 4: Larastan baseline burn-down (`UserOwnershipScope` ×4 incl. the live `dd()`, dead `menu_*`
  helpers ×7, `env()` outside config ×2, `fromData()` on the dead model-owned path ×4).
- Phase 5: `web/create` and `web/edit` are untested because `flux:date-picker` is Pro-only; no default
  layout ships.
