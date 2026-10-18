# Phase 1 — Baseline

**Status:** in progress · **Branch:** `claude/lumodels-production-01cqya` · **Started:** 2026-10-18

Read first: `docs/plans/lu-models-study.md` in `n30/products` (the study). §2 is what we keep,
§3 the live bugs, §5 the roadmap, §6.6 the two rules: *ship the inspector alongside the magic*
and *name the hop for what it does*.

## Baseline (measured 2026-10-18, `master` @ `19b1d21`, PHP 8.3.6)

| Measure | Result |
|---|---|
| Install | Succeeds only with `../lu_unite` present (path repo). `Lorisleiva\Actions\Concerns\AsAction` and `Thunk\Verbs\Event` are used on the only live path and declared nowhere — a clean `composer require` fatals on the first request (study §3). |
| Tests | 3 tests, 10 assertions, green. Plain PHPUnit, no Laravel container, no database; nothing exercises `fromData`, an Action, a Verb, a route or a view. |
| Coverage of `src/` | 0.53 % lines (4 / 760), 1 / 84 methods, 0 / 27 classes. |
| Pint (laravel preset, `--test`) | 32 of 32 PHP files would change — every file under `src/`, `tests/`, `config/`, `routes/`, `database/`. |
| PHPStan / Larastan | Plain PHPStan without the framework: level 0 → 205 errors in 26 files, level 5 → 225, level 8 → 503. Larastan 3.13 with Testbench + Laravel 12 installed (the honest number): level 0 → 29 errors in 11 files, level 1 → 64, level 2 → 70, level 5 → 76 in 17 files, level 6 → 334. **Decision: level 5 with a `phpstan-baseline.neon` burned down phase by phase**, most of it sits in code phases 2–4 rewrite (dynamic properties, `broadcastOn()` bodies with no return, the `Str` alias, `fromData()` on a class-string). Plain-PHPStan detail: Roughly 110 of the level-0 errors are framework symbols the package does not install (`request()`, `view()`, `auth()`, `event()`, `abort()`, `redirect()`, `session()`, the `Str` / `Validator` aliases, `Illuminate\Http\Request`) and disappear once `laravel/framework` is a dev dependency; the rest are real — 16 undefined (dynamic) properties, 5 `broadcastOn()` bodies with no return, unknown-class calls. |
| CI | lint-only: `composer validate` + `php -l`. The suite does not run in CI because `lucipher/unite` is a private path repo. |

## What phase 1 delivers

1. **A real test harness.** `orchestra/testbench` (v9 on Laravel 11, v10 on Laravel 12) booting the
   package provider against an in-memory SQLite database, with a small fixture app under
   `tests/Fixtures` (an `App\Models\*` set, policies, a user) autoloaded only for tests. Every later
   phase proves its claims through this harness.
2. **CI matrix.** GitHub Actions: PHP 8.2 / 8.3 / 8.4 × Laravel 11 / 12, `prefer-stable`; pcov
   coverage (text summary in the job log, Clover/HTML as an artifact); Pint in `--test` mode;
   Larastan. One required status check per matrix cell.
3. **Pint.** Laravel preset, `composer lint` / `composer lint:fix`. The first run reformats the tree
   in its own commit so later diffs stay readable.
4. **Larastan.** `composer analyse`. The level is the highest the *current* code passes honestly
   after fixing real defects it finds; what remains is recorded in `phpstan-baseline.neon` with a
   burn-down note, not hidden by lowering the level.
5. **Coverage report.** `composer coverage`; the CI floor starts at the measured number and only
   ratchets up. Phase 6 raises it to ≥ 90 %.
6. **Merge `fix/3.0.1-security`** (another session is finishing it) after a review against this
   checklist — each item is done only when a test fails before and passes after:
   - event names: `{model}.created` / `.updated` / `.deleted` fire for the action in scope, not
     `.updated` for everything (study §5.1);
   - the Action override resolves one string built once, under `App\LuModels\Actions\{folder}\…`
     (§5.2);
   - safety flags spelled as declared and **fail closed** — `$fillable` respected by default (§5.3);
   - routes method-scoped: `GET` cannot reach `store`/`update`/`destroy` (§5.4);
   - one Verb base class; `$service` assigned in all five; every `handle()` returns the service;
     Event override name built from the *model* (§5.5);
   - `index` on an empty table returns an empty page, not a 500.

## Why

The study's finding is that most of `src/` has never run in anger: all four transports ship
disabled, no consuming app turned them on, and the suite certifies guarantees the code does not
provide (`LuModelTest.php:34-37`). Nothing from phase 2 onward can be trusted without a harness
that boots the provider, hits a route and reads the database. The CI matrix is what lets the
"Laravel 11 / 12, PHP 8.2–8.4" promise be a fact rather than a line in `composer.json`.

## How it is verified

- `composer test` green locally (`../lu_unite` symlinked) and in every matrix cell.
- `composer lint` and `composer analyse` exit 0 locally and in CI.
- The coverage job prints a per-class table; the floor check fails the build if `src/` line
  coverage drops below the recorded number.
- The security-fix merge carries a failing-then-passing test per checklist item above; the PR
  description links each test to its study section.

## Constraints discovered while measuring

- **Private dependency in CI.** Until phase 2 makes `lucipher/unite` optional, the matrix job has
  to check out `N30/LUcipher` into `../lu_unite`, which needs a token with read access to that
  private repo (`secrets.LU_UNITE_TOKEN`). Without the secret the matrix job is skipped and only
  lint / static analysis run. Phase 2 removes the need.
- **The sandbox blocks GitHub archive downloads** for repositories outside this session's scope, so
  Composer installs here run from source (`git clone`), and pcov was built from source into the
  scratchpad. None of this affects CI, which has normal network access.
- `use-github-api: false` is set globally in this sandbox's Composer config. It is not committed.

## Findings while building the harness (scratchpad copy, 2026-10-18)

The harness was built on a copy of the tree in the session scratchpad so nothing landed in the
repository before `fix/3.0.1-security` exists. What it found, beyond the study:

1. **Livewire is a third undeclared dependency.** Registering the `/spa` route autoloads
   `Livewire\Master`, which extends `Livewire\Component`; without Livewire the provider cannot
   boot at all (`Class "Livewire\Component" not found` at `routes/lu_models-routes.php:48`).
2. **The blades are written against Flux Pro.** 22 `<flux:*>` tags, among them `flux:table`
   (+ `.columns/.column/.row/.cell`) and `flux:date-picker`, which are Pro-only. The package cannot
   require a paid, private-repository UI kit; phase 5 ships a plain Blade/Tailwind default theme and
   keeps Flux as an optional theme. 🔴 question for Neo below.
3. **unite's provider helper assumes the n30 directory layout.** `lu_addon_provider_register/boot`
   build the config and view paths as `__DIR__.'/../../../lu_models/…'` from *inside the unite
   package*, so this package only boots when its checkout is literally named `lu_models` and sits
   beside `lu_unite`. Under Composer's `vendor/lucipher/models` layout the config merge `require`s a
   missing file and fatals. This is why it worked on Neo's machine and nowhere else, and it is the
   strongest argument for phase 2 (the package registers its own config/views/components by its own
   `__DIR__`). The harness works around it with a `lu_models` symlink beside a real copy of unite.
4. **The class-based Blade components are never used.** unite registers the component namespace
   under the alias `models` (`Blade::componentNamespace(…, $app_name)`), while every blade writes
   `<x-lu_models::…>`. Blade therefore resolves the tags as *anonymous* components from the view
   namespace; `index.blade.php:27` passes `:dbColumns` which the class would map to `$db_columns`,
   the anonymous view sees `$dbColumns`, and **index with rows 500s** with `Undefined variable
   $db_columns`. Index on an empty table 500s earlier (`$data[0]`, study §3). So the index page has
   never rendered through this provider on any table, empty or not.
5. **`SHOW FULL COLUMNS` blocks every controller test on SQLite.** It is the first statement in
   `controller_initialize()`. The portable replacement (`Schema::getColumns()` + `getIndexes()`,
   mapped back to the `Field/Type/Null/Key/Default/Extra/Comment` shape the blades read) is
   written and verified in the scratch copy and is the first `src/` change after the merge; it is
   half of study §5.8 pulled forward because the harness needs it.
6. **The characterization suite reproduces the study's live bugs on SQLite** (11 tests, 7 red):
   `GET …/destroy/{id}` deletes the row; `PUT …/update/{id}` writes `secret_token` past
   `$fillable`; `post.created` and `post.deleted` never fire (`post.updated` fires for everything);
   `DELETE` removes the row and then fatals at `Verbs/Deletion.php:38` (`$this->service` null);
   index 500s empty and non-empty. These tests go into the repo with phase 1 and are the
   failing-then-passing proof for the `fix/3.0.1-security` review.

Dev dependencies the harness needed to boot the shipped code as-is: `orchestra/testbench`,
`livewire/livewire ^4`, `lorisleiva/laravel-actions ^2`, `hirethunk/verbs`. They are dev-only in
phase 1; phases 2 and 4 decide what the package actually requires.

## Baseline tooling

Measured with `laravel/pint` 1.32.1 and `phpstan/phpstan` 2.3.1 installed globally (Larastan 3.13 installed but not yet usable without a bootable app). Numbers are in the table above. Decision: the Larastan level is chosen after Testbench is in, on the real error list, not on this one.

## Decisions taken without asking (defaults; shout if wrong)

- Test harness is **Testbench**, not a hand-rolled container.
- Pint uses the **laravel** preset unchanged.
- The CI matrix uses **prefer-stable** only; a `prefer-lowest` lane can be added when the
  constraint floor matters (post-release).
- Coverage runs on **PHP 8.3 / Laravel 12** only; the other cells run tests without coverage.
