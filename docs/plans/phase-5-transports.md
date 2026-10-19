# Phase 5 — The transports

**Status:** done 2026-10-19 (report: `phase-5-report.md`) · **Depends on:** phase 4 · **Study:** §2.1–2.5, §2.7, §2.8, §5.13, §6.5, §7 Q6.

## What

One brain (the operations, the descriptor, the policies, the audit) and now **five** mouths: the JSON
API, the web pages, the REST-for-developers pages, the Livewire admin UI and an MCP server for agents.
Every mouth reads the same `Descriptor` and the same `schema()`; none of them re-derives anything.

1. **JSON API, REST-shaped.** Beside the action-shaped `/api/{model}/{action}/{id?}` routes (kept one
   release, deprecated) the API gains `GET /api/{model}`, `POST /api/{model}`, `GET|PATCH|PUT|DELETE
   /api/{model}/{id}`. One envelope: `{data, meta, links}` for a listing, `{data}` for a record,
   `{data, message}` for a write, Laravel's `{message, errors}` for 422. Query grammar on the listing:
   `filter[field]=value` and `filter[field][op]=value` (`eq`, `ne`, `lt`, `lte`, `gt`, `gte`, `like`,
   `in`, `null`) on readable fields only; `sort=-created_at,title` (readable fields only; `order` stays
   as the legacy alias); `page` and `per_page` (cap 200, from phase 4); `fields=title,body` (sparse
   fields, intersected with what the action may read); `include=user` (alias of `with`, descriptor
   relationships only); `search` and `search_fields` as today. A write accepts `?dry_run=1`: authorize,
   validate, answer `{would: "...", valid: true}` and write nothing. Unknown filter fields, sorts or
   includes are 400 with the offending key named, never ignored.
2. **OpenAPI 3.1.** `GET /api/openapi.json` (route `lu::api.openapi`, same middleware as the API, so a
   token client can fetch it) builds the document from the descriptors of every model exposed on `api`:
   one path item per REST-shaped route, parameters from `Index::schema()`, request bodies from
   `Create::schema()`/`Update::schema()`, response schemas from the fields readable on `show`,
   `components.schemas` per model plus `Error`, `ValidationError` and `Page`, `securitySchemes.sanctum`
   (bearer), `jsonSchemaDialect` 2020-12. `php artisan lu:openapi` writes the same document to a file.
3. **The UI seam: primitives, not themes.** Ten Blade components under `lu_models::ui.*` (button, input,
   textarea, select, checkbox, badge, table, pagination, heading, menu) render plain HTML with Tailwind
   classes by default and Flux's **free** components when `lu::models.ui.flux` is on and Flux is
   installed. The shipped blades use only these primitives; `flux:table` and `flux:date-picker` (Pro)
   go. The layout swap stays exactly as the author built it: one blade tree, `DynamicLayout`, the
   `/spa` renderer includes the web blade with the empty layout. Publishing the views
   (`lu-models-views`) remains the full-control path; `lu::models.ui.layout` is the host layout.
4. **Descriptor-driven blades.** The index applies `index_columns` (never applied before) and otherwise
   the fields readable on `index`; the forms render from `Descriptor::fieldsFor('create'|'edit')` with
   input kind, help text, max length and required from the descriptor; `show` renders the readable
   fields; the table's dotted relation columns and the per-row menu stay. A `lu_models::ui.toasts`
   component listens for the `lu-models:toast` browser event, so the toast no longer needs Flux Pro.
5. **Livewire 4 admin UI.** `Livewire\Master` is replaced by three components with typed parameters,
   mounted by the `/spa/{model}/{action?}/{id?}` route through one resolver that turns the URL into a
   model class, an action and a key at the entry point (study §6.5: derive the surface once, thread it
   down): `Livewire\Index` (sort, search, filters, pagination, delete), `Livewire\Form` (create and
   edit, validation errors by field, the model's rules through the same validator as the API),
   `Livewire\Show`. Each calls the operations through `LuModels::fromData()` with `controller_type`
   `spa`, exactly as the controllers do. Admin-only by default (🔴 Q6 default, phase 3):
   `spa_middleware` stays `['web', 'auth']`, policies are required as everywhere.
6. **MCP server.** `Mcp\LuModelsServer extends Laravel\Mcp\Server`, registered when `laravel/mcp` is
   installed and `lu::models.enable_mcp_routes` (default off) is on: `Mcp::web('/mcp/lu-models')`
   behind `['api', 'auth:sanctum', 'throttle:lu-models-mcp']` with the same fail-closed sanctum logic
   as the API, and `Mcp::local('lu-models')` for stdio. `boot()` builds one tool **instance** per model
   exposed on the new `mcp` transport and per operation it supports: `{slug}_list`, `{slug}_get`,
   `{slug}_create`, `{slug}_update`, `{slug}_delete`, whose `inputSchema` **is** `Action::schema()` and
   whose description is the model's help. Two fixed tools: `lu_models_describe` (the descriptor and the
   schemas of one model, what `lu:models` prints) and `lu_models_models` (the exposed models). Every
   write tool takes `dry_run` (default false): authorized and validated, it answers in the conditional
   ("Would create Post 'Alpha'") and writes nothing; a real write answers in the past tense ("Created
   Post #7"), the tense carrying the distinction (study §2.5, LU-NeoNos). `mcp` is the fifth transport:
   exposure lists it, policies receive it, the audit row records it, `rate_limits.mcp` throttles it.
7. **Security carried into the new mouths.** IDOR and mass-assignment suites run against the
   REST-shaped routes and the MCP tools; CSRF stays on web and rest; an MCP tool for a model not
   exposed on `mcp` is not listed and not callable; `filter[secret_token]` on a hidden field is 400
   (never a silent probe); sparse `fields` cannot name a hidden field.

## Why

The study's point of the package is "one brain, four mouths" (§2.1): the fork point confined to one
method. Phase 4 made the brain describable (`Descriptor`, `schema()`); phase 5 is where that pays:
the OpenAPI document, the form, the table and the MCP tool list are four renderings of the same
description, and cannot disagree. The MCP server is the "AI-agent interface" of the mission statement,
and dry run in the tense is the one idea from LU-NeoNos the study singles out as worth keeping.

## How verified

- Every item lands with its failing-then-passing tests. API: filtering, sorting, sparse fields,
  includes, envelope, 400 on unknown keys, dry run, OpenAPI structure and "every documented path
  routes". UI: `Livewire::test()` for the three components (sort, search, paginate, validation errors,
  create and edit round trips, delete) and HTTP tests on `/web` and `/spa` rendering the same blades.
  MCP: `LuModelsServer::tools()` lists exactly the exposed models' tools, `::tool()` for each
  operation, dry run writes nothing and leaves no audit row, authorization and 422 through the tools.
- The phase 3 security suites extend to the REST-shaped routes and the MCP tools.
- Coverage floor ratchets to the measured value at the end; Larastan stays at zero errors.

## Decisions taken without asking (defaults; shout if wrong)

- **`^12` only.** `laravel/mcp` 1.x needs `illuminate/json-schema ^12.41.1`, which Laravel 11 does not
  have. Phase 5 requires `illuminate/* ^12` and drops the Laravel 11 cells (and the EOL advisory
  override they needed) from CI. This is the default already taken for 4.0 (phase 1 🔴).
- REST-shaped routes are the documented API; the action-shaped ones stay one release, deprecated.
- The listing envelope changes from a raw paginator to `{data, meta, links}` on the `api` transport
  only; `web`, `rest` and `spa` are unaffected. No API clients exist yet.
- Plain ids are accepted on `show`, `edit`, `update` and `destroy` for an **authenticated** caller:
  policies are required since phase 3, so the encrypted-id obscurity (`allow_any_unencrypred_access`,
  default false) no longer buys safety and blocks the shipped UI's own links. Encrypted ids and slugs
  still work; guests still need the public opt-ins. The misspelled property keeps working as an alias.
- Plain HTML + Tailwind is the default theme; Flux free is a switch, Flux Pro is never required.
- MCP tool names are `{slug}_{verb}` (MCP requires `[A-Za-z0-9_-]`), verbs `list/get/create/update/delete`
  rather than the package's `index/show/store`, because agents read them cold.

## 🔴 Questions for Neo (defaults in force until answered)

- 🔴 Should `GET /api/openapi.json` be public (no auth) so tooling can fetch it before a token exists?
  Default: no, same middleware as the API; `lu::models.openapi_public` turns it on.
- 🔴 Does the MCP server expose **every** `api`-exposed model, or only models that list `mcp`?
  Default: only models that list `mcp` (`$lu_transports` or `lu::models.models`), so an agent surface
  is an explicit decision per model.

## Slices

| Slice | Commit | Tests |
|---|---|---|
| 5a | `^12`, `laravel/mcp` in require-dev and suggest, CI matrix without Laravel 11 | suite green on 8.2–8.4 × 12 |
| 5b | REST-shaped routes, envelope, filter/sort/fields/include, 400s, dry run | `Api/*Test` |
| 5c | OpenAPI 3.1 document and `lu:openapi` | `OpenApiTest` |
| 5d | UI primitives (plain + Flux free), descriptor-driven blades, `index_columns`, toasts, id scheme | `WebTransportTest` extended, `UiPrimitivesTest` |
| 5e | Livewire `Index`/`Form`/`Show`, the spa resolver, `Master` removed | `Livewire/*Test` |
| 5f | MCP server, tools, describe, dry run, fifth transport | `Mcp/*Test` |
| 5g | Security suites on the new mouths; report, changelog, floor | — |
