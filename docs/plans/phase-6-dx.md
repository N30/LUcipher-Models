# Phase 6 — Developer experience

**Status:** done 2026-10-19 (report: `phase-6-report.md`) · **Depends on:** phase 5 · **Brief:** `lu:install`, publishable config, README, examples app, upgrade guide, coverage ≥ 90 % on `src/`.

## What

1. **`php artisan lu:install`.** Publishes the config (and, with `--views`, the blades), publishes the
   audit migration, and prints the three things an app has to do next in its own words: expose a model
   (`lu::models.models` or `$lu_transports`), write its policy, turn a transport on. `--transports=web,api`
   writes those switches into the published config so the first page works without opening the file;
   `--mcp` adds the laravel/mcp step. Idempotent: a second run changes nothing and says so.
2. **The published config is the reference.** Every key in `config/lu/models.php` carries the sentence that
   explains it, in the order a reader meets the package (exposure and policies first, transports, then the
   grammar, UI, MCP, audit, rate limits, the legacy knobs last). `README.md` links to it rather than
   duplicating it.
3. **The examples app is a Workbench.** `workbench/` (Orchestra Workbench, already installed with
   Testbench) holds a small app: `Post` and `Comment` models with policies, a `User` with
   `is_super_admin()`, migrations, a seeder with a demo user and rows, a layout with the Tailwind CDN and
   Livewire's scripts, and `testbench.yaml` enabling web, api, spa and mcp. `vendor/bin/testbench serve`
   opens `/web/posts` signed in as the demo user; `vendor/bin/testbench lu:models` inspects it;
   `vendor/bin/testbench mcp:start lu-models` is the local MCP server. A smoke test boots the workbench's
   models and config inside the suite so the example cannot rot.
4. **`UPGRADE.md`.** 2.x → 3.0 (the rename, unite required, API fail closed), 3.0 → 3.0.1 (the security
   fixes and their upgrade notes, moved here from the changelog's prose), 3.0.x → 4.0 (Laravel 12 only,
   unite optional, `lorisleiva/laravel-actions` gone, Verbs as deprecated adapters, the typed events, the
   REST-shaped API and its envelope, plain keys for signed-in callers, the UI seam and Flux Pro gone,
   `Livewire\Master` and the `*FromData` hooks removed, the new config keys), each as a checklist an app
   can walk.
5. **README.** What the package is in one paragraph (describe a model once; a JSON API, an admin UI and an
   agent interface follow), install, a five-minute start, the five transports and what each needs, the
   listing grammar, dry run, OpenAPI, the admin UI and its two skins, the MCP server, the security model
   (exposure, policies, visibility, rate limits, audit), the override ladder, the inspector, testing, and
   versioning. The author's 2.x notes move to `UPGRADE.md`; his intent ("hit the ground running and
   override as needed") stays as the opening.
6. **Coverage and types.** `src/` is at 92.96 %; the weak classes come up: `ApiRestController` (36 %),
   `Support\Form` (68 %), `IndexTable` (70 %), `Actions\Create` (70 %), `LuModel` (79 %), `Writer`, `Keys`,
   `Scopes\UserOwnershipScope`, `Events\Index`/`Read`, `OpenApiCommand`. Goal: every class ≥ 80 %, `src/`
   ≥ 94 %, floor raised to 93. Larastan moves to level 6 with real docblocks (the 241 findings are iterable
   value types on the public API), no baseline.

## Why

Phases 2–5 made the package safe and complete; nobody but this session has installed it. The first
install is where a package earns or loses its reader: one command, one file that explains itself, one
app to look at, one page that says what to expect. The upgrade guide is owed to LU-Contractor's 2.x line.
Coverage above 90 % was the brief's floor; the weak classes are exactly the ones a host app overrides
(the rest controller, the form, the table), so they are the ones to pin.

## How verified

- `lu:install` has tests for a fresh app, an app with a published config, `--transports` and `--views`.
- The workbench smoke test renders `/web/posts`, `/api/posts`, `/spa/posts` and the MCP tool list from the
  workbench's own models and config.
- `README.md` and `UPGRADE.md` are read against the code: every command, route, config key and class
  named in them exists (a test greps the two files for backticked `lu::models.*` keys and `lu:*` commands
  and checks them against the config and the kernel).
- Coverage floor 93; Larastan level 6, zero errors.

## Decisions taken without asking (defaults; shout if wrong)

- The examples app is a Workbench inside this repository, not a second Laravel app with its own vendor.
- `lu:install` never edits an existing published config beyond the `enable_*_routes` switches it is asked
  to flip; everything else is the reader's.
- `UPGRADE.md` at the root, as Laravel packages do; `CHANGELOG.md` keeps the terse per-version list.

## 🔴 Questions for Neo (defaults in force until answered)

- 🔴 `composer.json` has no `authors`, `homepage` or `keywords`. Default: `authors` = Neo <neo@all1web.com>,
  `homepage` = the GitHub repository, keywords `laravel, crud, api, admin, mcp, livewire`.

## Slices

| Slice | Commit | Tests |
|---|---|---|
| 6a | `lu:install`, config comments pass | `InstallCommandTest` |
| 6b | the Workbench examples app, `testbench.yaml`, smoke test | `WorkbenchSmokeTest` |
| 6c | coverage on the weak classes, floor 93 | per class |
| 6d | Larastan level 6 | `composer analyse` |
| 6e | `README.md`, `UPGRADE.md`, docs consistency test, report | `DocsTest` |
