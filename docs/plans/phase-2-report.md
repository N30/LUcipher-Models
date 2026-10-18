# Phase 2 — Report

**Done:** 2026-10-18 · **Branch:** `claude/lumodels-production-01cqya` · Plan: `phase-2-standalone.md`.

## What landed

| Commit | What |
|---|---|
| `554f473` feat | The provider extends `Illuminate\Support\ServiceProvider` and registers config, views, Blade components (`lu_models`), the `LuModels` singleton (alias `LuModels`) and the routes from its own directory. The published `config/lu/models.php` is folded over the defaults in `register()` and again in `boot()`. `lucipher/unite` leaves `require` for `suggest`; its path repository is gone. `lorisleiva/laravel-actions` is in `require`; `livewire/livewire` and `livewire/flux` are `suggest`ed and the `/spa` transport refuses to register without Livewire, with a message. `Verb` no longer extends `Thunk\Verbs\Event`; `hirethunk/verbs` is gone. |
| `6ee4632` ci | The matrix and Larastan run on every push, no token, no sibling checkout; the test job asserts no `lucipher/*` package is installed. README gains an installation section. |
| (this commit) ci | Laravel 11 cells lift Composer's advisory block for that end-of-life line only. |

## Numbers

| Measure | Phase 1 | Phase 2 |
|---|---|---|
| Tests | 57 / 267 assertions | 62 / 285 assertions |
| `src/` line coverage | 60.15 % | 60.78 % (floor 58 %) |
| Larastan level 5 | clean against 21 baselined | clean against 21 baselined |
| Private dependencies | `lucipher/unite` (path repo) | none; `composer show lucipher/unite` fails in CI by design |
| Undeclared runtime dependencies | `AsAction`, `Thunk\Verbs\Event`, Livewire, Flux | none: `laravel-actions` required, Livewire/Flux suggested with a guard, Verbs base removed |

## What CI said

- Run 6 (`6ee4632`): preflight, Pint, Larastan and the three Laravel 12 cells (PHP 8.2 / 8.3 with
  coverage / 8.4) green. The three Laravel 11 cells could not install: Composer 2.10 blocks every
  `laravel/framework` 11.x release because the line is end-of-life (security fixes ended March 2026)
  and every release carries open advisories. The follow-up commit lifts the block for those cells only,
  with the reason written in the workflow. 🔴 for Neo: keep `^11.0|^12.0` and test an EOL line, or
  require `^12.0` in 4.0 (default: require `^12.0` in 4.0, keep 11 until then).

## Verified how

- `composer update` locally removes `lucipher/unite` and `hirethunk/verbs` from `vendor/`; the suite
  is green without them (62 tests); `composer validate --strict` passes.
- `ProviderBootsTest`: singleton + alias, class components under `lu_models`, no unite class loaded
  and `LU_PATH` undefined after boot, none of unite's environment variables set, Livewire guard.
- `PublishedConfigTest`: a `lu.models` array set before boot (as a published file would be) wins over
  the defaults; untouched keys keep theirs. This test is why the fold also runs in `boot()`: Testbench
  registers providers before `defineEnvironment()`, the reverse of a real app's order.

## Carried forward

- The sandbox symlinks (`/home/user/lu_unite`, `/home/user/lu_models`) are no longer needed; nothing
  in the repository refers to them.
- `use Str;` and the other facade aliases in `src/` still depend on the app's alias map (the class-level
  bootstrap registers them for tests). Phase 4 imports the classes.
- Flux stays in the blades until phase 5's default theme.
