# Phase 6 — Report

**Done:** 2026-10-19 · **Branch:** `claude/lumodels-production-01cqya` · Plan: `phase-6-dx.md`.

## What landed, in commit order

| Commit | What |
|---|---|
| `0892af8` feat | **`lu:install`**: config and audit migration published (blades with `--views`), `--transports` flips only those switches, `--mcp` adds the agent step, a second run changes nothing. **The config in reading order**, one sentence per key, same keys and defaults. |
| `d25a834` feat | **The examples app as a Workbench** (`composer serve`): Post (a LuModel with OwnedByUser, help, `index_columns`, `$lu_fields`, a hidden column) and Comment (plain, kept off the agents), policies, a demo user the workbench signs in as, every transport on; a smoke test. **Discovery works from any namespace**: slugs came from slicing the class name after `App\Models`; the relative name now comes from the file's path and the controllers resolve a slug through discovery first. The suite pins its cache, session and queue drivers. |
| `ca82f35` test | **The classes a host app overrides are pinned**: the rest controller, `Form::fields()` per column kind and the super admin's owner field, the table's dotted columns and cell kinds, the writer's injections, the unsafe create path, slugs and guests' opt-ins, discovery's helpers, the console scope. Two fixes: an unloaded relation in a dotted column no longer fails the page; `displayText()` returns iterables as JSON. Floor 95. |
| `787005c` chore | **Larastan level 6**, zero errors, no baseline: the legacy untyped surface documented (operations, service, controller, LuModel's knobs, events, Verbs, components), arrays with value types, relations with generics; three checks that could never be false removed with the stale API comment and the misspelled `$aciton`. |
| (this commit) docs | **README** rewritten around the one-paragraph promise and the five transports; **UPGRADE.md** (2.x → 3.0 → 3.0.1 → 4.0 as checklists); a test reads both against the config keys, commands, classes and routes they name. |

## Numbers

| Measure | Phase 5 | Phase 6 |
|---|---|---|
| Tests | 202 / 1188 assertions | 225+ / 1330+ assertions |
| `src/` line coverage | 92.96 % (floor 90) | 97.86 % (floor raised 90 → 95); every class ≥ 90 % |
| Larastan | level 5, clean | **level 6**, clean, no baseline, workbench analysed too |
| First-install path | read the config, guess | `lu:install`, `composer serve`, `lu:models` |
| Namespaces discovery handled | `App\Models` | any `models_path` |

## CI taught us

- **`vendor/bin/testbench` leaves a `.env` in the skeleton** (`CACHE_STORE=database`), and the test app
  loads it. The suite pins `cache.default`, `session.driver` and `queue.default` itself.
- **Seeding belongs after the migrations run**: `defineDatabaseMigrations()` only registers them; seed in
  `setUp()` after the parent.
- **`actingAs()` persists for the rest of a test**, in Laravel's, Livewire's and laravel/mcp's harnesses
  alike: a guest assertion goes first.
- **`LuModel` lists `id` and `created_at` by default** (its author's choice): a test that expects other
  columns in the table has to name them on the model.

## Decisions taken (defaults; shout if wrong)

- The examples app is a Workbench in this repository, not a second Laravel app.
- `lu:install` edits a published config only to flip the switches it is asked to.
- Larastan documents the overridable methods with docblocks rather than native return types, which
  would have broken every subclass an app already has.
- `composer.json` `authors`/`homepage`/`keywords` are left for Neo's answer (🔴 in the plan).

## Carried forward

- The MCP HTTP round trip is still tested at the route level (protocol headers per request); a client
  smoke test against the workbench server would close it.
- The Flux skin is tested by its markers, not visually.
- `LuModel`'s legacy knobs (`$index_relationships_columns`, `$default_loaded_relationships`,
  `$public_pages`, `$after_*_redirect`) are documented in code but have no dedicated tests beyond what the
  listing exercises.
