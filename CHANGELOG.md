# Changelog — lucipher/models

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
