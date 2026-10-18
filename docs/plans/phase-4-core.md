# Phase 4 — The core

**Status:** planned 2026-10-18 · **Depends on:** phase 3 · **Study:** §5.6–5.13, §4, §6.6.

## What

1. **One class per operation (§5.6).** `Create`, `Read`, `Update`, `Delete`, `Index` become plain
   invokable classes with constructor injection: each one authorizes, then performs the state change
   that its Verb used to own, as a protected method an app subclass can override. The Verbs folder
   stays only as a thin adapter (`Verb::handle()` delegating to the operation) for one release, marked
   deprecated, so an app override at `App\Models\Verbs\…` keeps running. `lorisleiva/laravel-actions`
   leaves `require` (🔴 Q2 default); `::run()` remains as a static shim on each operation.
2. **One Result out of every operation (§5.9).** `Result` carries the model or collection, the message,
   the validation errors, the redirect id and a `status`; each transport presents it (JSON,
   toast+redirect, flash+back, Livewire toast). The `recordOf()`/`redirectId()` guesswork goes.
3. **A cached model descriptor (§5.8).** `Descriptor::for(Model|string)` gathers in one place what the
   service, the blades and the API read piecemeal today: table, columns (type, nullable, default,
   comment → help text, max length), primary key, relationships, `$fillable`/`$hidden`,
   `$lu_fields`, visibility per action, validation rules per action, transports, policy class, route
   names. Built once per model class and cached (`lu::models.descriptor_cache`, default
   `cache.default`, busted by `lu:models --refresh` and on deploy), replacing the per-request schema
   read, `getMetaData()` and the reflection over every public method.
4. **`php artisan lu:models` (§5.7, §6.6).** For every discovered model: exposed transports, policy
   (and whether it exists), the resolved operation/view/event classes with DEFAULT or OVERRIDE per
   rung, the fields with their visibility, the rate limits and the audit state. `lu:models {model}`
   drills into one. `--json` for tooling. This is the inspector the study says must ship beside the
   magic.
5. **Typed, broadcastable events (§5.10).** `Created`/`Updated`/`Deleted` carry the model, the
   changed attributes and the transport, implement `ShouldBroadcast` behind
   `lu::models.broadcast` (default off) on a private channel named for the owner; `Index`/`Read`
   are dropped (a read is not an event). The string events stay (🔴 Q5 default).
6. **The ownership trait (§5.11).** `OwnedByUser` boots the observer and the global scope the package
   ships but never wires; the scope fails loudly for a guest instead of building `where user_id =
   null`; the live `dd()` goes.
7. **Dedupe index/show (§5.12).** `controller_show()` folds into `controller_index()` with a scope
   callback; the relationship auto-discovery and dotted-column flattening live once.
8. **`schema()` and `supports()` on operations (§5.13).** Each operation declares the arguments it
   accepts (from the descriptor) and the models it supports. This is what phase 5's MCP tools and
   OpenAPI document read.
9. **Housekeeping the baseline asked for:** the facade aliases in `src/` become imports; the dead
   `menu_*` helpers, the `lu_razor` reference and the never-implemented `*FromData` model hooks go;
   the Larastan baseline shrinks to zero or close.

## Why

The study's two rules. "Name the hop for what it does": five Actions calling five Verbs calling one
service to reach one `save()` is six frames for one write, and every hop re-passes the same four
untyped arguments. "Ship the inspector alongside the magic": four of the §3 bugs were silent-fallback
bugs that `lu:models` would have shown on day one. The descriptor is what makes phase 5 possible:
validation, OpenAPI, the admin UI and the MCP tools all read the same description of the model.

## How verified

- The 106-test suite keeps passing while the internals move: the transports' observable behaviour
  (routes, statuses, redirects, events, audit rows) is pinned by phases 1–3.
- New tests per item: operation classes directly (authorize-then-write, override seam), `Result`
  presenters per transport, descriptor contents and cache behaviour, `lu:models` output (DEFAULT vs
  OVERRIDE for a fixture with one override of each kind), broadcast channel and payload, the trait's
  scope and observer, `schema()`/`supports()` for each operation.
- Larastan level raised when the baseline allows; coverage floor raised to the measured value.

## Decisions taken without asking (defaults; shout if wrong)

- Operations keep the plain names Create/Read/Update/Delete/Index; "Creation/Revelation/Updation/
  Deletion/Annexation" survive one release as deprecated adapters.
- Descriptor cache store is the app's default cache; TTL is forever, busted explicitly.
- Broadcasting is off by default.
