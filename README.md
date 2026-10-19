# lucipher/models

Describe a model once. A JSON API, a server-rendered admin UI, a Livewire admin UI and an agent
interface (MCP) follow from that one description, all behind the model's own policy.

```php
// app/Models/Post.php — the whole description
class Post extends Model
{
    protected $fillable = ['title', 'body', 'published'];
    protected $hidden = ['internal_note'];                    // never reads out, on any transport
    public $lu_transports = ['web', 'api', 'spa', 'mcp'];     // where it is served
    public $lu_help = ['title' => 'Shown as the headline'];   // help text in forms, schemas and tools
    public $lu_fields = ['body' => ['show', 'create', 'edit']]; // body is for the detail page and forms
    public $store_validation_rules = ['title' => 'required|string|max:120'];
}
```

With a `PostPolicy` and `'enable_web_routes' => true`, that is `/web/posts` with a sortable, searchable
listing and forms built from the schema; `GET /api/posts?filter[published]=1&sort=-id&fields=title`
with an OpenAPI 3.1 document; `/spa/posts` live in Livewire; and `posts_list`, `posts_get`,
`posts_create`, `posts_update`, `posts_delete` tools for an MCP client, with dry run.

The idea its author shipped in 2.x stands: hit the ground running and override as needed. Every piece
is resolved by convention and can be replaced by a file with the predictable name; `php artisan
lu:models` prints what resolved.

## Requirements

PHP 8.2+, Laravel 12. Optional: `livewire/livewire` for `/spa`, `livewire/flux` (free) for the Flux skin,
`laravel/mcp` for the agent interface, Sanctum for the API's authentication.

## Install

```bash
composer require lucipher/models
php artisan lu:install            # config/lu/models.php, the audit migration; --views for the blades
php artisan migrate
```

`lu:install --transports=web,api` turns those on in the published config. Everything else in
`config/lu/models.php` is explained in the file itself, in the order you will meet it.

## Five minutes

1. **Expose a model.** In `config/lu/models.php`: `'models' => [App\Models\Post::class => ['web', 'api']]`,
   or on the model: `public $lu_transports = ['web', 'api'];`. Nothing is served until you do.
2. **Give it a policy.** `php artisan make:policy PostPolicy --model=Post`. A signed-in user on a model
   without a policy is denied, not let through. The policy receives the model, the input and the
   transport: `public function delete(User $user, Post $post, $data = null, ?string $transport = null)`.
3. **Turn a transport on** (`'enable_web_routes' => true`) and open `/web/posts`.
4. **Look at what resolved**: `php artisan lu:models Post`.

## The five transports

| Transport | Where | Needs | What it is |
|---|---|---|---|
| `web` | `/web/{model}/{action?}/{id?}` | `web_middleware` (`web`, `auth`) | Server-rendered pages: listing, show, create, edit. |
| `rest` | `/rest/{model}/{action?}/{id?}` | hardcoded `auth` + `web` | The same pages for developers and admins; writes answer with a flash and back. |
| `api` | `/api/{model}`, `/api/{model}/{id}` | Sanctum (fails closed) | JSON. `GET /api/openapi.json` describes it. |
| `spa` | `/spa/{model}/{action?}/{id?}` | `livewire/livewire`, `spa_middleware` | The same pages, live: search, sort, forms with errors by field. |
| `mcp` | `/mcp/lu-models` and `mcp:start lu-models` | `laravel/mcp`, Sanctum over HTTP | One tool per model and operation for agents, with dry run. |

Each is off until `enable_{transport}_routes` is true. A model is reachable on a transport only when
exposed on it; an unexposed model is a 404 like a model that does not exist.

## The JSON API

Routes: `GET|POST /api/{model}`, `GET|PUT|PATCH|DELETE /api/{model}/{id}` (names `lu::api.index`,
`lu::api.store`, `lu::api.show`, `lu::api.update`, `lu::api.destroy`), under `lu::models.api_prefix` if set.
The older `/api/{model}/{action}/{id?}` routes still answer for one release.

One envelope: `{data, meta, links}` for a page, `{data, meta: {count}}` for a flat `limit`, `{data}` for a
record, `{message, data, id}` for a write, Laravel's `{message, errors}` for 422.

| Parameter | Example | Meaning |
|---|---|---|
| `filter[field]` | `filter[published]=1` | equals |
| `filter[field][op]` | `filter[id][gte]=10`, `filter[title][like]=hello`, `filter[id][in]=1,2`, `filter[body][null]=1` | `eq ne lt lte gt gte like in null` |
| `sort` | `sort=-created_at,title` | `-` for descending |
| `fields` | `fields=id,title` | sparse fields (the key always) |
| `include` | `include=user,comments` | relationships to include |
| `search`, `search_fields` | `search=hello&search_fields[]=title` | contains, over readable fields |
| `page`, `per_page`, `limit` | `per_page=50` (max 200), `limit=5` (flat) | pagination or a flat list |

Every name is checked against what the action may read: an unknown, hidden or unreadable field,
operator or relationship is a **400 naming it**, never a silent probe.

A write with `dry_run=1` is authorized and validated and answers `{would: true, message: "Would create
Post", data}` without writing anything. A record is named by its plain key, its encrypted key, or its
slug; a guest may use only the encrypted key unless the model opts in.

## The admin UI

`/web`, `/rest` and `/spa` render the same blades. They are written against `lu_models::ui.*` primitives
that render plain HTML with Tailwind classes by default, or Flux's free components when
`lu::models.ui.flux` is true and `livewire/flux` is installed. `lu::models.ui.layout` names the host layout
the pages extend (it must `@yield('content')`). Publish the blades for anything more:

```bash
php artisan vendor:publish --tag=lu-models-views   # resources/views/vendor/lu_models
```

A view named after the model wins over the shipped one: `resources/views/posts/index.blade.php`.

The listing shows `public $index_columns` (a `LuModel` lists `id` and `created_at` unless it says
otherwise; a plain model lists what the index may read), sorts by its headers, and shows a related
record's attribute for a dotted column such as `user.name`. The forms come from the operation's schema:
which fields, which are required, how long, with the help text from `$lu_help` or the column comment.
Toasts after a write are flashed once; a Livewire component dispatches `lu-models:toast`.

## Agents (MCP)

```bash
composer require laravel/mcp
# config/lu/models.php: 'enable_mcp_routes' => true, and expose the model on 'mcp'
php artisan mcp:start lu-models          # a local server, running as lu::models.mcp.local_user
```

Over HTTP the server listens at `lu::models.mcp.path` (`/mcp/lu-models`) behind Sanctum. It offers
`lu_models_models` (what is exposed), `lu_models_describe` (a model's fields, rules and each tool's
schema), and per model `{slug}_list`, `{slug}_get`, `{slug}_create`, `{slug}_update`, `{slug}_delete`,
whose input schema is the operation's own. Writes take `dry_run: true` and then answer in the conditional
("Would delete Post #7") and change nothing. Refusals (403, 404, 400, 422) come back as tool errors the
agent can read. Every call runs through the same policies, audit and rate limits as the other transports,
and the policy sees `'mcp'` as the transport.

## The security model

- **Exposure.** Nothing is served until a model is listed in `lu::models.models` or declares
  `$lu_transports`. `expose_all` is a development switch.
- **Policies are required.** A signed-in user on a model without a registered policy gets a 403 naming
  the policy. `lu::models.super_admin_method` names a user method that bypasses policies (null turns it off).
- **Hidden never reads out.** `$hidden`, `$hidden_columns` and `$lu_fields` are honoured by the pages, the
  API, the search, the OpenAPI document and the tools. A write naming a key the model does not allow
  (not fillable, or kept from the action by `$lu_fields`) is refused whole, 422, naming the key.
- **The package owns `user_id`, `ip_address`, `user_agent` and `slug`** on create: whatever the payload says.
- **Rate limits** per transport and for writes (`lu::models.rate_limits`); **audit** of every write in
  `lu_models_audit` with the transport and the changes (never hidden values).
- **Keys.** A guest needs the encrypted key unless the model sets `$allow_public_access_by_id`,
  `$allow_public_access_by_slug` or `$allow_any_unencrypted_access`.

## Describing a model

Any Eloquent model works. Extending `All1\LuModels\Models\LuModel` adds the knobs below as declared
properties; a plain model declares the ones it needs.

| On the model | Meaning |
|---|---|
| `$fillable`, `$hidden` | what may be written, what never reads out |
| `$lu_transports` | the transports this model is served on |
| `$lu_fields` | `['body' => ['show', 'create', 'edit']]`: the actions a field takes part in |
| `$lu_help` | help text per field; the column comment is the fallback |
| `$index_columns` | the listing's columns (dotted `relation.attribute` allowed) |
| `$store_validation_rules`, `$update_validation_rules` | Laravel rules per write |
| `use OwnedByUser` | stamps `user_id` from the signed-in user and scopes every query to their rows |
| `$creation_safety = false`, `$update_safety = false` | opt out of `$fillable` for that write |
| `$public_pages` | actions guests may run on this model without a policy |

## Overriding

One idiom at every layer: does the app have a class or view with the predictable name?

| Layer | The app's file |
|---|---|
| Views | `resources/views/{plural}/{action}.blade.php` |
| An operation | `App\LuModels\Actions\{folder}\{Create\|Read\|Update\|Delete\|Index}{Model}` (extend the package's and override `perform()`) |
| An event | `App\Events\{folder}\{Model}{Created\|Updated\|Deleted}` |
| The policy | `App\Policies\{Model}Policy`, or `Gate::policy()` |

`{folder}` is the model's `public $action_folder` (`CRUD` by default). The string events
`{model}.created`, `{model}.updated`, `{model}.deleted` fire on every write; the typed
`All1\LuModels\Events\Created`, `Updated`, `Deleted` broadcast when `lu::models.broadcast.enabled` is on.

## The inspector

```bash
php artisan lu:models            # every discovered model: transports, policy, overrides
php artisan lu:models Post       # one model: the override ladder (DEFAULT or OVERRIDE per rung), fields, rules
php artisan lu:models --json     # the descriptors, for tooling
php artisan lu:openapi [path]    # the OpenAPI 3.1 document
```

Four of the bugs that shipped in 3.0 were silent fallbacks the inspector would have shown on day one.

## The examples app

```bash
composer serve     # vendor/bin/testbench serve — opens /web/posts signed in as demo@example.test
```

`workbench/` is a small application: two models, two policies, a seeder, every transport on.
`vendor/bin/testbench lu:models` inspects it; `vendor/bin/testbench mcp:start lu-models` is its MCP server.

## Contributing

```bash
composer test       # PHPUnit (Testbench feature suite + class-level unit suite)
composer coverage   # with a floor CI enforces (.coverage-floor)
composer lint       # Pint
composer analyse    # Larastan, level 6, no baseline
```

CI runs PHP 8.2, 8.3 and 8.4 on Laravel 12. A fix is not done without a failing-then-passing test.

## Versioning

- **4.0 (this branch)** is the production line: Laravel 12, `lucipher/unite` optional, the five
  transports. `UPGRADE.md` walks 2.x → 3.0 → 3.0.1 → 4.0; `CHANGELOG.md` has every change.
- **3.0.x** is security and correctness fixes on the 3.0 design.
- **2.x** is the legacy line existing apps (LU-Contractor) were built against: pin `v2.0.0` or the
  `2.x` branch; legacy fixes land there only.

Proprietary. © LuCipher.
