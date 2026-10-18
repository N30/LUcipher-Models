# Phase 2 — Stand alone

**Status:** planned 2026-10-18 · **Depends on:** phase 1 (done) · **Decides:** study §7 Q1 (default:
unite optional).

## What

1. **The provider registers itself.** `LuModelsServiceProvider` extends
   `Illuminate\Support\ServiceProvider` and registers config, views, Blade components (`lu_models`),
   the `LuModels` singleton and the routes from its own `__DIR__`. The published
   `config/lu/models.php` keeps working: it is folded over the package defaults under `lu::models`,
   which is what unite's helper did.
2. **`lucipher/unite` becomes optional.** Moved from `require` to `suggest`; the `../lu_unite` path
   repository leaves `composer.json`. Nothing in `src/` references `All1\LuUnite`.
3. **No environment side effects.** The package never calls `putenv()`, `useEnvironmentPath()` or
   touches `DB_CONNECTION` / `SESSION_DOMAIN`. A test snapshots the environment before boot and
   compares after.
4. **Honest runtime dependencies.** `lorisleiva/laravel-actions` and `hirethunk/verbs` are on the
   only live write path today, so they move to `require` until phase 4 decides their fate (🔴 Q2,
   Q3 default: dropped). Livewire is needed only when `enable_spa_routes` is on and Flux only by the
   shipped blades: both stay in `suggest`, and enabling the SPA transport without Livewire fails
   with a clear message instead of a class-not-found.
5. **Integration point for unite apps.** None is needed in code: a unite app installs the package
   the same way and keeps its published config. The README documents that, plus how unite's
   `lu_addon_provider_*` conventions map onto the standard `vendor:publish` tags
   (`lu-models-config`, `lu-models-views`).
6. **CI without the gate.** The matrix and Larastan jobs run on every push; the `LU_UNITE_TOKEN`
   plumbing goes away.

## Why

- The study's Q1 and the products launch checklist (B1, 2026-09): the package could not be installed
  on Laravel Cloud because both it and unite were out-of-repo path packages, so ALL1.AI removed it.
- unite's helper builds this package's paths as `__DIR__.'/../../../lu_models/…'` from inside unite, so
  the package only ever booted when its checkout was named `lu_models` beside `lu_unite`
  (phase 1 report). A package that depends on the name of its parent directory is not installable.
- unite's provider `putenv()`s `DB_CONNECTION`, `DB_DATABASE`, `SESSION_DOMAIN`, `APP_URL` and more on
  every boot. A CRUD package must not do that to its host.

## How verified

- The whole suite runs with `lucipher/unite` absent from `vendor/` (already shown in the scratch dry
  run: same results, provider boots).
- `ProviderBootsTest` grows: config merged, published override wins, views and components resolve,
  routes registered per transport, singleton bound, environment unchanged by boot.
- A test enables `spa` routes without Livewire and asserts the message.
- CI matrix green on all six cells without any secret.
- `composer validate --strict` passes; `composer show --tree` lists no `lucipher/*` package.

## Not in this phase

- Dropping `AsAction` / Verbs (phase 4, §5.6), the default theme and layout (phase 5), per-model
  route enablement and required policies (phase 3).

## Decisions taken without asking (defaults; shout if wrong)

- Publish tags are `lu-models-config` and `lu-models-views`.
- The provider no longer extends `LuUniteServiceProvider`. An app that subclassed the package
  provider to reach unite's helpers would break; none is known.
