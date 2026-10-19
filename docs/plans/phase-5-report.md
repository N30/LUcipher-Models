# Phase 5 — Report

**Done:** 2026-10-19 · **Branch:** `claude/lumodels-production-01cqya` · Plan: `phase-5-transports.md`.

## What landed, in commit order

| Commit | What |
|---|---|
| `c1a03d2` build | **Laravel 12 only.** `laravel/mcp` 1.x needs `illuminate/json-schema` (12.41+); Laravel 11 is end of life. `illuminate/* ^12`, Testbench `^10`, the three Laravel 11 cells and their advisory override leave the matrix. `laravel/mcp` is a dev dependency and a suggestion. |
| `9a9cd73` feat | **The REST-shaped JSON API.** `GET\|POST /api/{model}`, `GET\|PUT\|PATCH\|DELETE /api/{model}/{id}` (`lu::api.index\|store\|show\|update\|destroy`), `api_prefix` honoured, the action-shaped routes deprecated for one release, the id slot never an action name. One envelope: `{data, meta, links}`, `{data, meta: {count}}`, `{data}`. The grammar: `filter[field][op]`, `sort`, `fields`, `include`, beside `search`, `page`, `per_page`, `limit`, `order`; every name checked against what the action may read, 400 naming the offender. `dry_run` on writes: authorized, validated, `{would: true, message: "Would create Post"}`, nothing written. Plain keys for signed-in callers (policies decide; guests still need the encrypted key or an opt-in). The controllers read route parameters by name: positional binding had swapped `action` and `id` on the member routes. |
| `7cfbd7f` feat | **OpenAPI 3.1** at `GET /api/openapi.json` (behind the API's auth unless `openapi.public`) and `lu:openapi`, from the same descriptors and `schema()`s the code runs on; a test walks every documented path and method through the router. `Index::schema()` describes the grammar. |
| `04e732a` feat | **The UI seam.** `lu_models::ui.*` primitives render plain HTML with Tailwind classes, or Flux's free components when `ui.flux` is on; the shipped blades use only them (Flux Pro's date-picker gone). **Descriptor-driven pages:** `index_columns` applied at last, headers sort, rows link by plain key; `show` renders what show may read; the forms come from `Form::fields()`, the operation's `schema()` plus input kind and help text. The toast is flashed, not put. |
| `ef5b4d0` test | Livewire, Flux and MCP providers registered by name in the suite: discovery booted Flux locally and not in CI, where the old blades' `<flux:*>` tags had been rendering as raw HTML unnoticed. |
| `23bfaa0` feat | **The Livewire admin UI.** `/spa/{model}/{action?}/{id?}` resolves slug and action once (`SpaController`) and mounts `Livewire\Index`, `Form` or `Show` with typed parameters; they render the `/web` blades with the empty layout. `Master` is gone. **`Support\Writer`** is the one write pipeline behind API, web, rest and Livewire; **`Support\Keys`** resolves plain, encrypted and slug keys; the listing query takes its grammar as data. The empty layout no longer `@extends`. |
| `8ccdeb6` feat | **The MCP server.** One tool per model exposed on `mcp` and per operation (`posts_list`, `posts_get`, `posts_create`, `posts_update`, `posts_delete`) whose input schema is the operation's `schema()`, plus `lu_models_models` and `lu_models_describe`; reads in the API's envelope (`Support\Envelope`, shared), writes through `Writer` with `dry_run` in the conditional; refusals as tool errors. HTTP behind Sanctum at `mcp.path`, local as `mcp:start lu-models` running as `mcp.local_user`. `mcp` is the fifth transport. |
| (this commit) test | IDOR, mass assignment and exposure on the REST routes, the spa pages and the MCP tools. The spa edit form now authorizes on mount, not only on save. |

## Numbers

| Measure | Phase 4 | Phase 5 |
|---|---|---|
| Tests | 147 / 683 assertions | 202 / 1188 assertions |
| `src/` line coverage | 85.17 % (floor 83) | 92.96% (floor raised 83 → 90) |
| Larastan level 5 | clean, no baseline | clean, no baseline |
| Transports | 4 (web, rest, api, spa) | 5 (+ mcp), one `Writer`, one `Envelope`, one `Keys` |
| What a client can read about the contract | `schema()` | OpenAPI 3.1, `lu_models_describe`, the form, all from one descriptor |
| Laravel lines | 11 and 12 | 12 |

## CI taught us

- **Testbench's package discovery is environment-dependent.** It booted Flux locally and not in CI; the
  suite registers the optional providers by name when their classes exist.
- **Inside a Blade component tag, `$component` is Blade's variable** for the current component; a view
  variable of that name is shadowed. The spa shell passes `$livewireComponent`.
- **A public zero-argument method on a class component becomes a deferred view variable** that shadows
  `with()` data of the same name and cannot be passed as a component attribute (`IndexTable::headers()`).
- **A nested `@extends` shares the section stack** with the page around it and replaces the page's content
  with the fragment's. The empty layout is a plain slot now.
- **Livewire's test harness turns aborts into responses**, not exceptions (`->assertForbidden()`), and both
  Livewire's and laravel/mcp's `actingAs()` keep the user on the guard for the rest of the test: guest
  assertions come first. `old()` is null without a session.
- **Laravel's positional controller binding** hands route parameters over in registration order; the
  REST-shaped routes carry `model`, `id` and a default `action`, so `$action` received the id. The
  controllers read parameters by name.

## Decisions taken (defaults; shout if wrong)

- `^12` only; the action-shaped API routes stay one release; the envelope changed on `api` only.
- Plain keys for signed-in callers everywhere; guests keep the encrypted-key rule and the model's opt-ins.
- `models => [Post::class => true]` means every transport, `mcp` included: an explicit `true` is an explicit
  decision. A list (`['web', 'api']`) is the way to keep a model off the agents' surface.
- MCP tool verbs are `list/get/create/update/delete`; refusals are tool errors, not protocol errors.
- The OpenAPI document sits behind the API's auth (🔴 default); `openapi.public` opens it.
- A local MCP server runs as `mcp.local_user` or as a guest; there is no implicit super user.

## Carried forward

- The MCP HTTP round trip is tested at the route level only (`ValidateMcpHeaders` wants protocol headers
  per request); the tools are tested through laravel/mcp's harness. A client-level smoke test belongs
  with the examples app in phase 6.
- The Flux skin is tested by its markers (`data-flux-*`), not visually; the plain skin has no screenshot.
- `index_relationships_columns` still constrains eager loads only on the listing; `include` on `show`
  loads whole relations.
- Larastan level 6 (iterable docblocks), the README, the examples app and the upgrade guide: phase 6.
