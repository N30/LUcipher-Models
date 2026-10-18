# Phase 4 — Report

**Done:** 2026-10-18 · **Branch:** `claude/lumodels-production-01cqya` · Plan: `phase-4-core.md`.

## What landed, in commit order

| Commit | What |
|---|---|
| `44f90ab` refactor | **One class per operation.** `Actions\Action` is the base of `Index`, `Read`, `Create`, `Update`, `Delete`: `handle()` authorizes then `execute()`s; `execute()` runs a legacy Verb override when the app has one, else `perform()`, then fires the string event and records the audit row. `perform()` is the override seam for a subclass; `App\LuModels\Actions\{folder}\{Action}{Model}` still replaces the whole operation. The five Verbs survive one release as `@deprecated` adapters. `lorisleiva/laravel-actions` leaves `require`; `::run()` is a static shim. Six frames to one `save()` become three. |
| `4955833` refactor | **One `Result` out of every write**, presented per transport (JSON with status, toast + redirect, flash + back, Livewire toast). The redirect after `store`/`update` carries the encrypted key, the only id `show` accepts by default; 3.0.x redirected to the plain id and landed on a 403. The API `store` payload is the created record, not the serialized service. |
| `eb6a412` feat | **A cached model `Descriptor`.** Everything the service, the blades and the API read piecemeal, gathered once per model class and cached under `lu_models.descriptor.{class}`; `controller_initialize()` stops reading the schema per request. `legacyColumns()` produces the column shape the blades still read. |
| `89f62bf` feat | **`php artisan lu:models`**, the inspector beside the magic (study §5.7, §6.6): per model the transports, the policy (MISSING in red when exposed without one), the override ladder with DEFAULT or OVERRIDE per rung, every field with its visibility and help text, relationships, rules, rate limits, audit state; `--json`, `--refresh`. |
| `12bfb01` feat | **Typed, broadcastable events.** `Created`/`Updated`/`Deleted` extend `ModelEvent`, broadcast behind `lu::models.broadcast` (off) on the owner's private channel or the record's channel, payload without hidden attributes. 3.0.x imported `ShouldBroadcast` and left `broadcastOn()` empty. `Events\Index`/`Read` still fire, deprecated. |
| `ac414d8` feat | **`OwnedByUser`** wires the observer and the global scope the package shipped but never registered. Guests see no rows (`1 = 0`, not `where user_id = null`); the live `dd()` and the `env()` hacks are gone. |
| `e3cbb99` + `64097d9` refactor | **One query behind index and show** (`runQuery()`). The duplicate hid two bugs: a `search_fields` naming a hidden column let a `LIKE` probe leak its contents (the search now covers only readable fields; naming none matches nothing), and a bad `order` was a 500 (falls back to `index_default_order`, which 3.0.x declared and ignored). `per_page` capped at 200; non-array `with` is 400; `show` 404s instead of dereferencing `$data[0]`. |
| `864e921` feat | **`schema()` and `supports()`** on every operation: a JSON Schema 2020-12 subset of what it accepts (writable fields minus the injected `user_id`/`ip_address`/`user_agent`/`slug` for create and update, the key for read and delete, the query parameters for index) and whether the model is exposed on a transport. Building it exposed that the descriptor fell `update` rules back to the store rules while the validator never did; the descriptor now says what the request gets. |
| `118a950` chore | **Housekeeping.** Facade aliases become imports (and `tests/bootstrap.php` goes); dead `menu_*`/`lu_razor` helpers go; the `*FromData` hooks go (the controllers tested for `createFromData` and then called `fromData` with another arity, so any model defining one fatalled). Larastan boots the provider through `testbench.yaml` and analyses the fixture app: the baseline is empty and deleted. |

## Numbers

| Measure | Phase 3 | Phase 4 |
|---|---|---|
| Tests | 106 / 439 assertions | 147 / 683 assertions |
| `src/` line coverage | 74.53 % (floor 72) | 85.17 % (floor raised 72 → 83) |
| Larastan level 5 | clean, 21 baselined | clean, **no baseline** (fixtures analysed too) |
| Frames from a controller to `save()` | 6 (Action → Verb → service → …) | 3 |
| Schema reads | one per request | one per model class, cached |
| What a transport can learn about an operation | nothing | `schema()`, `supports()`, the descriptor, `lu:models` |

## CI taught us

- **A chained shell step committed past a failure.** `e3cbb99` went out with a Larastan error because an
  edit step in the same chain failed and the commit ran anyway. Rule since: verify, then commit in a
  separate step; never chain a commit after an edit that can fail.
- **The class-level harness has to be honest about the framework.** Running the operations without an
  application needed a real guard (`auth()`'s return type), an encrypter, and a bound database manager
  for the schema facade. The descriptor now defaults safely when cache, gate or resource path are not
  bound, which is also what makes it usable from a plain console.
- **`expectsOutputToContain` matches one substring per written line.** The inspector tests read
  `Artisan::output()` instead.
- **Laravel rules come in two spellings** (`'a|b'` and `['a', 'b']`); `Descriptor::ruleList()` is the one
  normaliser. The TypeError that taught this was caught by the schema tests before the push.

## Decisions taken (defaults; shout if wrong)

- `Update::schema()` requires nothing: input is partial, and that is what the validator enforces.
- The package-injected fields (`user_id`, `ip_address`, `user_agent`, `slug`) are left out of the write
  schemas so a client never sends what the server overwrites.
- Verbs stay one release as adapters; `Events\Index`/`Read` stay one release, deprecated.
- `LuModel::fromData()` (the instance delegate) stays: harmless and public since 3.0.
- Larastan stays at level 5. Level 6 would add 241 findings, nearly all missing iterable type docblocks:
  a documentation campaign that belongs with the DX phase, not with core changes.
- The coverage floor is set to 83, two points under the measured value, so a matrix cell cannot flap.

## Carried forward

- Phase 3's list stands: `/spa` (Livewire `Master`) untested until phase 5 rewrites it; `show` by plain
  id requires `allow_any_unencrypred_access`; `index_columns` not applied; the create/edit blades use
  `flux:date-picker` (Flux Pro) and are untested.
- Larastan level 6 (iterable type docblocks across `src/`) in phase 6.
- The service's public untyped state (`$controller_data`, `$data`, `$filters`, …) is what the blades read;
  phase 5's descriptor-driven UI is the moment to type or retire it.
- The string events (`lu_models.{verb}.{model}`) remain beside the typed ones; a release note in phase 7
  decides whether 4.0 keeps both.
