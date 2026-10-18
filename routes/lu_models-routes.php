<?php

use All1\LuModels\Http\Controllers\Api\ApiResourceController;
use All1\LuModels\Http\Controllers\Api\ApiRestController;
use All1\LuModels\Http\Controllers\ResourceController;
use All1\LuModels\Livewire\Master;
use All1\LuModels\Providers\LuModelsServiceProvider;
use All1\LuModels\Routing\ResourceRoutes;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

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
    $apiPrefix = trim('api/'.trim((string) config('lu::models.api_prefix'), '/'), '/');
    $registerApi = function (): void {
        ResourceRoutes::register('', ApiResourceController::class, 'lu::api.resources', false, writeMiddleware: ['throttle:lu-models-writes']);
        ResourceRoutes::registerRest('', ApiResourceController::class, 'lu::api', writeMiddleware: ['throttle:lu-models-writes']);
    };
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
        Route::any('spa/{model}/{action?}/{id?}', Master::class)->name('lu::spa.resources');
    });
}
