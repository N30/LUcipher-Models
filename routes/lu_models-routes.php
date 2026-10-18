<?php

use All1\LuModels\Http\Controllers\Api\ApiResourceController;
use All1\LuModels\Http\Controllers\Api\ApiRestController;
use All1\LuModels\Http\Controllers\Api\OpenApiController;
use All1\LuModels\Http\Controllers\ResourceController;
use All1\LuModels\Http\Controllers\SpaController;
use All1\LuModels\Mcp\LuModelsServer;
use All1\LuModels\Providers\LuModelsServiceProvider;
use All1\LuModels\Routing\ResourceRoutes;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;

// Route::middleware(['auth'])->group(function () {
if (config('lu::models.enable_web_routes')) {
    Route::middleware([...(array) config('lu::models.web_middleware'), 'throttle:lu-models-web'])->group(function () {
        // v3.0.1: method-scoped. GET index/create/show/edit, POST store, PUT|PATCH update, DELETE destroy
        // (3.0.0 used Route::any, so GET web/{model}/destroy/{id} deleted the row past CSRF).
        ResourceRoutes::register('web', ResourceController::class, 'lu::web.resources', writeMiddleware: ['throttle:lu-models-writes']);
    });
}
if (config('lu::models.enable_rest_routes')) {
    Route::middleware(['auth', /***** enforced as REST is just for developers and admin ******/ 'web', 'throttle:lu-models-rest'])->group(function () {
        ResourceRoutes::register('rest', ApiRestController::class, 'lu::rest.resources', writeMiddleware: ['throttle:lu-models-writes']);
    });
}
if (config('lu::models.enable_api_routes')) {
    // /api[/{api_prefix}]: the REST-shaped routes (GET|POST /{model}, GET|PUT|PATCH|DELETE /{model}/{id})
    // are the documented API; the action-shaped /{model}/{action}/{id?} routes stay one release, deprecated.
    // /openapi.json is registered first so no {model} route can shadow it.
    $apiPrefix = ResourceRoutes::apiPrefix();
    $openapiPublic = (bool) config('lu::models.openapi.public');
    $registerOpenApi = function (): void {
        Route::get('openapi.json', OpenApiController::class)->name('lu::api.openapi');
    };
    $registerApi = function () use ($openapiPublic, $registerOpenApi): void {
        if (! $openapiPublic) {
            $registerOpenApi();
        }
        ResourceRoutes::register('', ApiResourceController::class, 'lu::api.resources', false, writeMiddleware: ['throttle:lu-models-writes']);
        ResourceRoutes::registerRest('', ApiResourceController::class, 'lu::api', writeMiddleware: ['throttle:lu-models-writes']);
    };
    if ($openapiPublic) {
        Route::prefix($apiPrefix)->middleware(['api', 'throttle:lu-models-api'])->group($registerOpenApi);
    }
    if (array_key_exists('auth:sanctum', App::make(Router::class)->getMiddleware())) {
        Route::prefix($apiPrefix)->middleware(['api', 'auth:sanctum', 'throttle:lu-models-api'])->group($registerApi);
    } else {
        // v3.0: fail closed. 2.x registered these routes UNAUTHENTICATED when sanctum was missing.
        // If you genuinely need unauthenticated API resources, opt in via lu::models.allow_unauthenticated_api.
        if (config('lu::models.allow_unauthenticated_api')) {
            Log::warning('lu_models: auth:sanctum middleware not found — API resource routes are UNAUTHENTICATED (lu::models.allow_unauthenticated_api enabled)');
            Route::prefix($apiPrefix)->middleware(['api', 'throttle:lu-models-api'])->group($registerApi);
        } else {
            Log::warning('lu_models: auth:sanctum middleware not found — API resource routes NOT registered (set lu::models.allow_unauthenticated_api to override)');
        }
    }
}
if (config('lu::models.enable_spa_routes')) {
    LuModelsServiceProvider::assertLivewireInstalled();
    // Route::middleware(['auth'])->group(function () {
    Route::middleware([...(array) config('lu::models.spa_middleware'), 'throttle:lu-models-spa'])->group(function () {
        // one GET route; the controller resolves the slug and the action once and mounts the matching
        // Livewire component with typed parameters (study §6.5). Writes go through Livewire or the web routes.
        Route::get('spa/{model}/{action?}/{id?}', SpaController::class)->where('action', 'index|create|show|edit')->name('lu::spa.resources');
    });
}
if (config('lu::models.enable_mcp_routes')) {
    LuModelsServiceProvider::assertMcpInstalled();
    // The fifth mouth: the same operations, policies, audit and rate limits, for agents. HTTP fails closed
    // like the API (sanctum, or the explicit unauthenticated opt-in); the local server is for a developer's
    // own machine and runs as lu::models.mcp.local_user.
    $mcpPath = trim((string) (config('lu::models.mcp.path') ?: 'mcp/lu-models'), '/');
    if (array_key_exists('auth:sanctum', App::make(Router::class)->getMiddleware())) {
        Mcp::web($mcpPath, LuModelsServer::class)->middleware(['api', 'auth:sanctum', 'throttle:lu-models-mcp']);
    } elseif (config('lu::models.allow_unauthenticated_api')) {
        Log::warning('lu_models: auth:sanctum middleware not found — the MCP server is UNAUTHENTICATED (lu::models.allow_unauthenticated_api enabled)');
        Mcp::web($mcpPath, LuModelsServer::class)->middleware(['api', 'throttle:lu-models-mcp']);
    } else {
        Log::warning('lu_models: auth:sanctum middleware not found — the MCP server is NOT registered over HTTP (set lu::models.allow_unauthenticated_api to override)');
    }
    Mcp::local('lu-models', LuModelsServer::class);
}
