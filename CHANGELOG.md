# Changelog — lucipher/models

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

### Staying on 2.x
Pin the `v2.0.0` tag or the `2.x` branch. Bug fixes for legacy apps land on `2.x` only.
