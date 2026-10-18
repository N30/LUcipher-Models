<?php

return [
    'layout' => 'components.layouts.app',
    'api_prefix' => '', // like v1 it goes right after /api
    'lang_prefix' => '', // language file prefixx (folder) like 'lu.'
    'web_middleware' => ['web', 'auth'],
    // 'api_middleware'=> [this one is mandatory for security reasons, some models may need access publicly but API never without a token]
    'spa_middleware' => ['web', 'auth'],

    // Which models the transports may serve. Nothing is exposed until a model is listed here
    // (class => ['web', 'rest', 'api', 'spa'], or true for every transport) or declares
    // `public $lu_transports = ['web', 'api'];` itself. The two are a union.
    'models' => [
        // App\Models\Post::class => ['web', 'api'],
    ],
    // Development convenience: expose every model under app/Models on every enabled transport.
    // Policies are still required. Never leave this on in production.
    'expose_all' => false,

    // Authorization: a model needs a registered policy to be reached by a signed-in user; a model
    // without one is denied. The user method below bypasses policies (the LuCipher convention);
    // set it to null to turn the bypass off.
    'super_admin_method' => 'is_super_admin',

    // If you avorride action clasess you might need to implement these in your own or use your own logic
    // Also individual model [not-built yet] settings will override these!
    // Also policies will override these!!! [about to build now]
    'public_index_models' => [],
    'public_create_models' => [],
    'public_update_models' => [],
    'public_read_models' => [],
    'public_delete_models' => [],

    // default redirect after save or destroy
    // model specific settings will override these NOTICE it can also be back
    'after_store_redirect' => 'show', // show, edit, index, create, back
    'after_destroy_redirect' => 'index', // show, edit, index, create, back
    'after_update_redirect' => 'show', // show, edit, index, create , back

    // if you want to defined your own so you can place where conditions on routes or such
    'enable_api_routes' => false, // protected only with sanctum
    // v3.0: when sanctum is not installed API routes are NOT registered unless this is true (2.x registered them unauthenticated)
    'allow_unauthenticated_api' => false,
    'enable_web_routes' => false, // protected  with Auth and Policies and above settings
    'enable_spa_routes' => false, // protected  with Auth and Policies and above settings
    'enable_rest_routes' => false, // protected  with Auth and Policies

];
