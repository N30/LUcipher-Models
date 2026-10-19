# Upgrading lucipher/models

Each section is a checklist. `CHANGELOG.md` has the per-version detail; `php artisan lu:models` shows
what resolved after you are done.

## 3.0.x → 4.0

**Requirements**
- [ ] Laravel 12 (`illuminate/* ^12`). Laravel 11 is end of life and is no longer supported.
- [ ] `lucipher/unite` is optional: nothing to change, but the package no longer requires it.
- [ ] `lorisleiva/laravel-actions` is no longer required. The operations' `::run()` static keeps its
      signature; an app that used the Actions' other faces (dispatch, as a listener) must add the
      package itself.

**Routes and the API**
- [ ] The documented API is REST-shaped: `GET|POST /api/{model}`, `GET|PUT|PATCH|DELETE /api/{model}/{id}`
      (`lu::api.index|store|show|update|destroy`). The action-shaped `/api/{model}/{action}/{id?}` routes
      still answer in 4.0 and go in 4.1.
- [ ] The listing answers `{data, meta, links}` (a flat `limit`: `{data, meta: {count}}`), `show`
      answers `{data: {...}}` (3.0.x answered a one-row array), a write answers `{message, data, id}`.
- [ ] A client that relied on unknown query parameters being ignored will see 400s: `filter`, `sort`,
      `fields` and `include` are checked against what the action may read.
- [ ] `lu::models.api_prefix` is honoured (it was declared and ignored).

**Keys**
- [ ] A signed-in caller may use plain keys on `show`, `edit`, `update` and `destroy`. Guests keep the
      3.0.x rule (encrypted key unless the model opts in). `$allow_any_unencrypred_access` is spelled
      `$allow_any_unencrypted_access`; the misspelling is still read.

**Exposure and policies** (since 3.1-security, kept in 4.0)
- [ ] List every model to serve in `lu::models.models` or give it `$lu_transports`. Nothing is served
      otherwise.
- [ ] Every exposed model needs a registered policy for signed-in users, or they are denied.
- [ ] Run `php artisan migrate` for the audit table (`lu:install` publishes the migration).

**Overrides**
- [ ] `App\Models\Verbs\{folder}\{Model}{Creation|Updation|Deletion|Revelation|Annexation}` overrides still
      run in 4.0 through the deprecated `Verbs\*` adapters and stop in 4.1. Move the logic to
      `App\LuModels\Actions\{folder}\{Create|Update|Delete|Read|Index}{Model}` extending the package's
      class and overriding `perform()`.
- [ ] The `createFromData` / `readFromData` / `updateFromData` / `deleteFromData` model hooks are gone
      (they never worked: the controller called a method with another arity). Use the operation override.
- [ ] `Events\Index` and `Events\Read` still fire and are deprecated; a read is not a state change.
- [ ] `App\Events\{folder}\{Model}Created|Updated|Deleted` overrides still resolve.

**UI**
- [ ] `Livewire\Master` is gone. `/spa/{model}/{action?}/{id?}` keeps its URL shape and now mounts
      `Livewire\Index`, `Livewire\Form` or `Livewire\Show`. A published `spa/master.blade.php` is unused.
- [ ] The blades use `lu_models::ui.*` primitives; Flux is optional (`lu::models.ui.flux`) and Flux Pro is
      never needed. Re-publish the views (`--tag=lu-models-views --force`) if you had published the 3.0.x
      ones: they referenced `flux:date-picker` and the removed partials.
- [ ] `index_columns` is applied to the listing at last. A `LuModel` without its own list shows `id` and
      `created_at`; declare the columns you want.
- [ ] The host layout named by `lu::models.ui.layout` (or `layout`) must `@yield('content')`.

**Config**
- [ ] New keys: `ui`, `openapi`, `mcp`, `enable_mcp_routes`, `rate_limits.mcp`, `descriptor_cache`,
      `models`, `expose_all`, `models_path`, `super_admin_method`, `rate_limits`, `broadcast`, `audit`.
      Run `php artisan lu:install` on a copy to see the file in reading order.

## 3.0.0 → 3.0.1

- [ ] Update fails closed. A model that relied on the unguarded update path must opt out explicitly with
      `public $update_safety = false;`. Models without `$fillable` throw `MassAssignmentException` on update,
      as a `LuModel` already did on create: declare `$fillable`.
- [ ] Routes are method-scoped: `GET` index/create/show/edit, `POST` store, `PUT|PATCH` update, `DELETE`
      destroy. A published `web/create` or `rest/create` blade that still sends `@method('PUT')` must drop it.
- [ ] The write routes are named `{name}.store`, `{name}.update`, `{name}.destroy` beside the GET name;
      `route('lu::web.resources', [...])` still generates every action's URL.
- [ ] `{model}.created` / `.updated` / `.deleted` fire for the right action (3.0.0 fired `.updated` for all).
- [ ] The app Action override is `App\LuModels\Actions\{folder}\{Action}{Model}`; the app Event override
      is looked up by the model's name (`App\Events\{folder}\{Model}Created`, not `CreationCreated`).

## 2.x → 3.0

- [ ] The package is `lucipher/models` (was `n30/lu_models`); update `composer.json`. 3.0 required
      `lucipher/unite ^3.0`; 4.0 makes it optional again.
- [ ] **API routes fail closed.** Without `auth:sanctum` the `/api` routes are not registered unless
      `lu::models.allow_unauthenticated_api` is true. 2.x registered them unauthenticated with a log line.
- [ ] `LuModel::getLuModels()` and `getNonLuModels()` return what their names say (2.x had them inverted).
- [ ] Staying on 2.x: pin `v2.0.0` or the `2.x` branch. Legacy fixes land there only.
