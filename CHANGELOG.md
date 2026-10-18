# Changelog — lucipher/models

## Unreleased

### Added
- Testbench-based feature suite with a fixture app beside the class-level unit suite; `composer test`,
  `composer coverage`, `composer lint`, `composer analyse` scripts.
- GitHub Actions CI: preflight, Pint, a PHP 8.2–8.4 × Laravel 12 matrix with pcov coverage and a
  coverage floor, Larastan at level 5 with no baseline (the analysis boots the package provider through
  `testbench.yaml` and covers the fixture app, so the view namespace and the ownership trait resolve).
- **One class per operation.** `Actions\Index`, `Read`, `Create`, `Update`, `Delete` extend `Actions\Action`:
  `handle()` authorizes then `execute()`s; `perform()` owns the state change and is the method an app
  subclass overrides. `App\LuModels\Actions\{folder}\{Action}{Model}` still replaces the whole operation.
  `::run()` keeps the signature `lorisleiva/laravel-actions` gave these classes.
- **One `Result` out of every write** (`Support\Result`): the record, the message, the validation errors
  (422), a 404 for a missing record and the created record's encrypted key. The API answers it as JSON,
  web as a toast and a redirect, rest as a flash and back, Livewire as a toast.
- **A cached model `Descriptor`** (`Support\Descriptor::for()`): table and columns (type, input kind,
  nullability, default, max length, keys, help text from `public $lu_help` or the column comment),
  fillable/hidden, per-action visibility, relationships, transports, policy, validation rules per action,
  the resolved override ladder with its source and the model's options. Cached under
  `lu_models.descriptor.{class}` (`lu::models.descriptor_cache`); `cache:clear` drops it.
- **`php artisan lu:models`**, the inspector: every discovered model with its transports, policy (MISSING
  when exposed without one) and overrides; `lu:models {model}` drills into the override ladder (DEFAULT or
  OVERRIDE per rung), the fields, relationships, rules, rate limits and audit state; `--json`, `--refresh`.
- **Typed, broadcastable events.** `Events\Created`, `Updated`, `Deleted` extend `Events\ModelEvent` (record,
  input, transport, changed attributes) and broadcast behind `lu::models.broadcast` (off by default) on the
  owner's private channel or the record's channel; the payload never carries hidden attributes.
- **`Concerns\OwnedByUser`** wires the ownership observer and the global scope the package shipped but never
  registered: `user_id` is stamped from the signed-in user, every query is scoped to their rows, guests see
  none (`1 = 0`, not `where user_id = null`), console commands are not scoped, `ownerKeyName()` picks the column.
- **A REST-shaped JSON API.** `GET|POST /api/{model}` and `GET|PUT|PATCH|DELETE /api/{model}/{id}`
  (route names `lu::api.index|store|show|update|destroy`), honouring `lu::models.api_prefix`. One
  envelope: `{data, meta, links}` for a page, `{data, meta: {count}}` for a flat `limit`, `{data}` for a
  record, `{message, data, id}` for a write, Laravel's `{message, errors}` for 422. The listing takes
  `filter[field]=value` and `filter[field][op]=value` (`eq`, `ne`, `lt`, `lte`, `gt`, `gte`, `like`, `in`,
  `null`), `sort=-created_at,title`, `fields=title,body`, `include=user` (alias of `with`), beside the
  existing `search`, `search_fields`, `page`, `per_page`, `limit` and `order`. Every name is checked
  against what the action may read: an unknown, hidden or unreadable field, operator or relationship is a
  400 naming it, never a silent probe.
- **OpenAPI 3.1.** `GET /api/openapi.json` (`lu::api.openapi`, behind the API's auth unless
  `lu::models.openapi.public`) and `php artisan lu:openapi [path]` describe every model exposed on `api`:
  paths per REST-shaped route, the listing's parameters from `Index::schema()`, request bodies from
  `Create::schema()`/`Update::schema()`, response schemas from the fields each action may read, `Error`,
  `ValidationError`, `PageMeta`, `PageLinks`, `DryRun`, and the Sanctum bearer scheme. `Index::schema()`
  itself now describes `filter`, `sort`, `fields` and `include`.
- **Dry run.** A write to the API with `dry_run=1` is authorized and validated, answers
  `{would: true, message: "Would create Post", data}` and writes nothing: no row, no event, no audit
  entry. `Result::would()`, `Action::dryRun()` and `Action::would()` carry it; browser transports ignore it.
- **`schema()` and `supports()` on every operation.** `schema($model)` is a JSON Schema 2020-12 subset of
  what the operation accepts (writable fields minus what the package injects for create/update, the key
  for read/delete, the query parameters for index); `supports($model, $transport)` follows exposure.

### Fixed
- The schema is read through Laravel's schema builder instead of MySQL's `SHOW FULL COLUMNS`, so the
  package works on SQLite and Postgres.
- Class-based Blade components are registered under the `lu_models` alias the blades use; the index
  page rendered nothing before because the components resolved as anonymous ones.
- The index filters take the model from the controller instead of the first row (empty tables).
- `authorize()` passes the model, the input and the transport to the policy; `can()` had been dropping
  the last two.
- `LuModel::viewFolder()` resolves the app's view folder (it read an undefined property).
- The five Events' `broadcastOn()` return an empty array instead of nothing.
- PHP 8.2 dynamic-property deprecations in `IndexTable` and `Livewire\Master`.

### Security
- The index search covers only fields the action may read: `search_fields` naming a hidden column let a
  `LIKE` probe leak its contents; a request naming none of the readable fields now matches nothing.
- **Nothing is exposed until a model says so.** A model is reachable on a transport only when listed in
  `lu::models.models` or declaring `public $lu_transports`; `expose_all` is a development switch. An
  unexposed model is a 404. A signed-in user on a model without a registered policy is denied (403
  naming the policy); the super-admin bypass is `lu::models.super_admin_method` (null disables it).
- **Hidden never reads out, guarded keys are rejected.** `$hidden`, `hidden_columns` and the new
  per-field `$lu_fields` map are honoured by the service, the blades and the API; a write naming a key the
  model does not allow written fails validation (422) instead of being dropped; `update()` validates.
- **Rate limits** per transport and for writes (`lu::models.rate_limits`), via `throttle`.
- **Audit log** of every create, update and delete (`lu_models_audit`, shipped migration).
- Regression suites for CSRF, IDOR and mass assignment on every transport.

### Changed
- The API transport answers 422 (validation, by field), 404 (missing record) and JSON 200 otherwise.
- Controllers re-initialize per request; `show`/`edit` 404 for a missing record.
- Model discovery reads `lu::models.models_path` (default `app_path('Models')`).
- `web/show` and `web/edit` close their slots; the layout component renders named slots; the edit forms
  use the package's super-admin check instead of the host app's `isAdmin()`.
- The two empty 2025 migration stubs are removed; the package loads its own migrations.
- **`lucipher/unite` is optional.** The provider extends `Illuminate\Support\ServiceProvider` and
  registers config, views, Blade components, the `LuModels` singleton and the routes from its own
  directory, so the package installs from Packagist or any VCS repository and no longer depends on the
  name of its parent folder. A published `config/lu/models.php` is still honoured. The package sets no
  environment variable. Publish tags: `lu-models-config`, `lu-models-views`.
- **Requires Laravel 12** (`illuminate/* ^12`). Laravel 11 reached end of life in March 2026 and
  `laravel/mcp`, which the MCP transport builds on, needs `illuminate/json-schema` (12.41+). `laravel/mcp`
  is `suggest`ed and installed for development.
- `lorisleiva/laravel-actions` is no longer required: the operations are plain classes. `livewire/livewire`
  (needed only by the `/spa` transport) and `livewire/flux` (used by the shipped blades) are
  `suggest`ed; turning `enable_spa_routes` on without Livewire fails with a clear message.
- `index` and `show` run one query (`LuModels::runQuery()`): eager loads from the model's list or the
  descriptor's relationships, the request's `with`, search, order, pagination or a flat limit, then
  visibility. `show` 404s on a missing record; a bad `order` falls back to the model's
  `index_default_order` (DESC for a `LuModel`, as declared and never applied in 3.0.x); `per_page` is
  capped at 200; a non-array `with` is 400.
- The API `store` payload's `data` is the created record (3.0.x serialized the whole service object); the
  redirect after a web `store`/`update` carries the encrypted key, the only id `show` accepts by default.
- `controller_initialize()` reads its column list from the descriptor instead of the schema on every request.
- The descriptor's `update` rules are the model's `update_validation_rules` only, as the validator applies
  them; the inspector and `schema()` no longer show store rules on a partial update.
- Facade aliases in `src/` and the routes (`use Str;`, bare `Route::`, `App::`, `Log::`) are imports.
- **Plain keys for signed-in callers.** `show`, `edit`, `update` and `destroy` accept the plain key, the
  encrypted key or a slug from a signed-in caller; the policy decides what they reach. A guest still needs
  the encrypted key unless the model opts in (`$allow_public_access_by_id`, `$allow_public_access_by_slug`,
  or `$allow_any_unencrypted_access` for both; the 3.0.x misspelling is read as an alias). 3.0.x refused
  plain keys for everyone, so the shipped index could not reach its own show and edit pages. A value that
  neither decrypts nor matches is a 404, not a 500.
- `show` returns the record (the API answers `{data: {...}}`); 3.0.x returned a one-row collection.

### Deprecated
- The action-shaped API routes `/api/{model}/{action}/{id?}` (`lu::api.resources`) answer for one more
  release; the REST-shaped routes are the documented API and what the OpenAPI document describes.
- `Verbs\Creation`, `Updation`, `Deletion`, `Revelation`, `Annexation` are adapters over the operations'
  `perform()` for one release: `App\Models\Verbs\{folder}\{Model}{Verb}` overrides and direct callers keep
  working. Override `App\LuModels\Actions\{folder}\{Action}{Model}` instead.
- `Events\Index` and `Events\Read` still fire but a read is not a state change; listen to the typed events.

### Removed
- The `createFromData`/`readFromData`/`updateFromData`/`deleteFromData` model hooks: the controllers tested
  for them and then called `fromData()` with another arity, so a model that defined one fatalled.
- The dead `menu_*` helpers and the `lu_razor` view reference (they called methods that did not exist).
- `Verb` no longer extends `Thunk\Verbs\Event`: the base class was never fired through and contributed
  one unused property and an undeclared dependency (`hirethunk/verbs`), which is gone.
- The whole tree is formatted with Pint (laravel preset).
- `composer.json` no longer carries a `version` field; tags carry the version.

## 3.0.1 — security and correctness fixes (2026-10-18)

### Security
- Update fails closed: `Updation` tested an undeclared `updation_safety`, so every update wrote every submitted key past `$fillable`; it now reads `update_safety`, and any model that does not set a safety flag to `false` (plain Eloquent models included) writes through `create()`/`update()` and `$fillable` (same default for `creation_safety`). `Revelation` no longer tests the misspelled `revelation_safety`; `read_safety` and `delete_safety` are declared but reserved — nothing reads them yet.
- Routes are method-scoped: `GET` index/create/show/edit, `POST` store, `PUT|PATCH` update, `DELETE` destroy on `/web`, `/rest` and `/api` (3.0.0's `Route::any` let `GET …/destroy/{id}` delete a row past CSRF); unknown path actions no longer route, and the controllers refuse a mismatched method with a 405.

### Fixed
- `{model}.created` / `{model}.updated` / `{model}.deleted` now fire for create / update / delete as the README documents (3.0.0 fired `{model}.updated` for every action); index and read fire no string event.
- The app Action override is one class name, `App\LuModels\Actions\{action_folder}\{Action}{Model}`, checked and run (3.0.0 checked that name but instantiated `App\Models\Actions\{Action}{Model}`).
- The five Verbs share an abstract `Verb` base with one constructor: `$service` is always assigned (delete no longer fatals), every `handle()` returns the service (update returned the model), properties are declared (no PHP 8.2 dynamic-property deprecations).
- The app Event override is looked up by the model's name, `App\Events\{action_folder}\{Model}{Created|Updated|Deleted|Read|Index}` (3.0.0 used the Verb's name, e.g. `CreationCreated`).
- Index on an empty table no longer 500s on `$data[0]`; it returns an empty result with the table's columns.
- Web update and destroy redirect again: `ResourceController` read `$result['data']->id`, which became a 500 ("Undefined property LuModels::$id") once the Verbs returned the service; it now takes the id from the record (and redirects through the transport's GET route name), including on "Record not found".

### Upgrade notes
- The shipped create forms now submit `store` as `POST` (they sent `PUT`); a published copy of `web/create` or `rest/create` that still sends `@method('PUT')` must drop it.
- The write routes are named `{name}.store`, `{name}.update` and `{name}.destroy` beside the existing GET name (e.g. `lu::web.resources`); `route('lu::web.resources', [...])` still generates every action's URL.
- A model that relied on the unguarded update path must now opt out explicitly with `public $update_safety = false;`.
- Models without `$fillable` now throw `MassAssignmentException` on update, as a `LuModel` already did on create in 3.0.0: `LuModel` declares neither `$fillable` nor `$guarded`, so Eloquent's default `$guarded = ['*']` applies. Declare `$fillable`, or set `public $update_safety = false;` to keep 3.0.0's write-every-key update. A plain Eloquent model (no safety flags) now goes through `$fillable` on create and update too; it opts out with `$creation_safety = false` / `$update_safety = false`.
- API responses: `PUT …/update/{id}` still returns the updated model in `data` (as in 3.0.0); `DELETE …/destroy/{id}`, which fataled in 3.0.0, returns the deleted model in `data`. `POST …/store` is unchanged (`data` is the service, `id` the encrypted key).

### Tests
- Class-level suite for every fix above (`illuminate/routing`, `illuminate/events` and `illuminate/config` added as dev dependencies; `tests/bootstrap.php` supplies the framework helpers for the test run only).
- The web and API write actions are driven through the controllers past the method guard, with warnings turned into exceptions as Laravel does, so a 500 after a successful write fails the suite.

## 3.0.0 (2026-08-04)

**Major version. Breaking changes — existing apps should stay on the `2.x` branch / `v2.0.0` tag until ready to upgrade.**

### Breaking
- Package renamed `n30/lu_models` → `lucipher/models` (a `replace` entry keeps the old
  names resolvable, but consumers should update their `require`). Now explicitly requires
  `lucipher/unite ^3.0`, PHP ^8.2 and illuminate/support ^11|^12.
- **Security — API routes fail closed.** In 2.x, if the `auth:sanctum` middleware was not
  registered, `/api/{model}/{action}/{id?}` routes were registered **unauthenticated** with
  only a log warning. In 3.0 they are not registered at all unless the new
  `lu::models.allow_unauthenticated_api` config flag is explicitly set to `true`.
- **Bug fix that changes behavior:** `LuModel::getLuModels()` and `LuModel::getNonLuModels()`
  had inverted filters in 2.x (each returned the other's set, which also swapped the contents
  of `menu_lu_models()` / `menu_non_lu_models()`). They now match their names.

### Fixed
- `LuModel::getModels()` no longer references an undefined variable when the app has no models.
- Internal `$responce` typo variables renamed.

### Added
- `illuminate/database` declared as a real dependency (LuModel extends Eloquent).
- PHPUnit test suite (`composer test`; run locally — CI is lint-only because the
  lucipher/unite dependency lives in a private repo, resolved via a `../lu_unite` path repo).

### Staying on 2.x
Pin the `v2.0.0` tag or the `2.x` branch. Bug fixes for legacy apps land on `2.x` only.
