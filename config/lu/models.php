<?php

/*
 * lucipher/models — describe a model once; a JSON API, an admin UI and an agent interface follow.
 * Everything here is read at request time. In the order you will meet it: what is exposed and who may
 * reach it, the transports, the API, the UI, the agents, the record keeping, the knobs 3.0.x had.
 */
return [

    /*
     | Exposure. Nothing is served until a model is listed here (class => the transports, or true for all
     | five: web, rest, api, spa, mcp) or declares `public $lu_transports = ['web', 'api'];` itself. The two
     | are a union. An unexposed model is a 404 on every transport, like a model that does not exist.
     */
    'models' => [
        // App\Models\Post::class => ['web', 'api'],
    ],

    // Development only: expose every model under models_path on every enabled transport. Policies are
    // still required. Never leave this on in production.
    'expose_all' => false,

    // Where discovery looks for models (lu:models, expose_all, the navigation list). null means
    // app_path('Models'); the namespace is read from each file, so any folder works.
    'models_path' => null,

    /*
     | Authorization. Every operation asks the model's policy (index, show, create, update, delete) with the
     | model, the input and the transport. A signed-in user on a model without a registered policy is denied.
     | The user method named here bypasses policies (the LuCipher convention); null turns the bypass off.
     */
    'super_admin_method' => 'is_super_admin',

    /*
     | Transports. Each one is off until you turn it on. web: server-rendered pages. rest: the same pages for
     | developers and admins (hardcoded auth + web). api: JSON, behind Sanctum, fails closed. spa: the Livewire
     | admin UI (needs livewire/livewire). mcp: the agent interface (needs laravel/mcp).
     */
    'enable_web_routes' => false,
    'enable_rest_routes' => false,
    'enable_api_routes' => false,
    'enable_spa_routes' => false,
    'enable_mcp_routes' => false,

    'web_middleware' => ['web', 'auth'],
    'spa_middleware' => ['web', 'auth'],

    // When auth:sanctum is not registered, the API and MCP routes are NOT registered unless this is true
    // (2.x registered them unauthenticated and only logged it). Policies still apply when it is.
    'allow_unauthenticated_api' => false,

    /*
     | The JSON API. GET|POST /api[/{api_prefix}]/{model}, GET|PUT|PATCH|DELETE .../{model}/{id}, and
     | GET .../openapi.json describing every model exposed on api (OpenAPI 3.1). The document sits behind the
     | API's own auth unless public; its title defaults to "{app.name} API".
     */
    'api_prefix' => '', // e.g. 'v1'
    'openapi' => [
        'public' => false,
        'title' => null,
        'version' => '1.0.0',
    ],

    /*
     | The UI. The shipped blades use the lu_models::ui.* primitives: plain HTML with Tailwind classes by
     | default, Flux's free components when flux is on and livewire/flux is installed. ui.layout is the host
     | layout the pages extend (the older `layout` key still works). Publish the views (--tag=lu-models-views)
     | for anything more. lang_prefix prefixes every __() key the blades use, e.g. 'lu.'.
     */
    'ui' => [
        'flux' => false,
        'layout' => null,
    ],
    'layout' => 'components.layouts.app',
    'lang_prefix' => '',

    /*
     | The MCP server (needs laravel/mcp): one tool per model exposed on mcp and per operation, plus
     | lu_models_models and lu_models_describe. Over HTTP at mcp.path (Sanctum, fails closed like the API);
     | locally as `php artisan mcp:start lu-models`, running as mcp.local_user (a user id) else as a guest.
     */
    'mcp' => [
        'path' => 'mcp/lu-models',
        'name' => null, // defaults to "{app.name} models"
        'local_user' => null,
    ],

    /*
     | Rate limits: requests per minute per signed-in user (per IP for guests) on each transport, and a
     | separate budget for writes on all of them. null turns a limiter off. Enforced by Laravel's throttle
     | middleware through the named limiters lu-models-{transport} and lu-models-writes.
     */
    'rate_limits' => [
        'web' => 120,
        'rest' => 120,
        'api' => 60,
        'spa' => 120,
        'mcp' => 60,
        'writes' => 20,
    ],

    /*
     | Audit. Every create, update and delete through the package is one row in lu_models_audit: model, key,
     | action, transport, user, IP and the changed attributes (never hidden ones). store_values=false keeps
     | only the attribute names. Run `php artisan migrate` after installing; a missing table logs a warning
     | and never blocks the write.
     */
    'audit' => [
        'enabled' => true,
        'store_values' => true,
        'table' => 'lu_models_audit',
    ],

    /*
     | Events. The typed Created / Updated / Deleted events broadcast as lu-models.{action} when enabled: on
     | the owner's private channel (App.Models.User.{user_id}) when the record has a user_id, else on the
     | record's channel lu-models.{table}.{key}. `channel` overrides the name with {table}, {key}, {user_id}.
     | The payload never includes hidden attributes. The string events {model}.created etc. always fire.
     */
    'broadcast' => [
        'enabled' => false,
        'channel' => null,
    ],

    /*
     | The descriptor: everything the package derives about a model (columns, help text, visibility,
     | relationships, policy, overrides), built once per model class and cached. null store means the default
     | cache. Clear it with `php artisan lu:models --refresh`, Descriptor::flush(), or cache:clear.
     */
    'descriptor_cache' => [
        'enabled' => true,
        'store' => null,
    ],

    /*
     | Public opt-ins (3.0.x). A model listed under public_{action}_models lets guests run that action without
     | a policy; a model's own `public $public_pages` does the same. Policies win when a user is signed in.
     | Where a web write redirects afterwards: show, edit, index, create or back.
     */
    'public_index_models' => [],
    'public_create_models' => [],
    'public_update_models' => [],
    'public_read_models' => [],
    'public_delete_models' => [],

    'after_store_redirect' => 'show',
    'after_update_redirect' => 'show',
    'after_destroy_redirect' => 'index',

];
