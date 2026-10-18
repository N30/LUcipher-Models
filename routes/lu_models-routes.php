<?php

use All1\LuModels\Http\Controllers\Api\ApiResourceController;
use All1\LuModels\Http\Controllers\Api\ApiRestController;
use All1\LuModels\Http\Controllers\ResourceController;
use All1\LuModels\Livewire\Master;
use All1\LuModels\Routing\ResourceRoutes;
use Illuminate\Routing\Router;

// use All1\LuModels\Http\Controllers\LuModelsController;

// Route::get('/lu_models', [LuModelsController::class, 'index'])->name('lu_models.index');
// Route::get('/lu_models/create', [LuModelsController::class, 'create'])->name('lu_models.create');
// Route::post('/lu_models', [LuModelsController::class, 'store'])->name('lu_models.store');
// Route::get('/lu_models/{lu_model}', [LuModelsController::class, 'show'])->name('lu_models.show');
// Route::get('/lu_models/{lu_model}/edit', [LuModelsController::class, 'edit'])->name('lu_models.edit');
// Route::put('/lu_models/{lu_model}', [LuModelsController::class, 'update'])->name('lu_models.update');
// Route::delete('/lu_models/{lu_model}', [LuModelsController::class, 'destroy'])->name('lu_models.destroy');

// Route::middleware(['auth'])->group(function () {
if (config('lu::models.enable_web_routes')) {
    Route::middleware(config('lu::models.web_middleware'))->group(function () {
        // v3.0.1: method-scoped. GET index/create/show/edit, POST store, PUT|PATCH update, DELETE destroy
        // (3.0.0 used Route::any, so GET web/{model}/destroy/{id} deleted the row past CSRF).
        ResourceRoutes::register('web', ResourceController::class, 'lu::web.resources');
    });
}
if (config('lu::models.enable_rest_routes')) {
    Route::middleware(['auth', /***** enforced as REST is just for developers and admin ******/ 'web'])->group(function () {
        ResourceRoutes::register('rest', ApiRestController::class, 'lu::rest.resources');
    });
}
if (config('lu::models.enable_api_routes')) {
    if (array_key_exists('auth:sanctum', App::make(Router::class)->getMiddleware())) {
        Route::prefix('api')
            ->middleware(['api', 'auth:sanctum'])
            ->group(function () {
                ResourceRoutes::register('', ApiResourceController::class, 'lu::api.resources', false);

            });
    } else {
        // v3.0: fail closed. 2.x registered these routes UNAUTHENTICATED when sanctum was missing.
        // If you genuinely need unauthenticated API resources, opt in via lu::models.allow_unauthenticated_api.
        if (config('lu::models.allow_unauthenticated_api')) {
            Route::prefix('api')->middleware('api')->group(function () {
                Log::warning('lu_models: auth:sanctum middleware not found — API resource routes are UNAUTHENTICATED (lu::models.allow_unauthenticated_api enabled)');
                ResourceRoutes::register('', ApiResourceController::class, 'lu::api.resources', false);
            });
        } else {
            Log::warning('lu_models: auth:sanctum middleware not found — API resource routes NOT registered (set lu::models.allow_unauthenticated_api to override)');
        }
    }
}
if (config('lu::models.enable_spa_routes')) {
    // Route::middleware(['auth'])->group(function () {
    Route::middleware(config('lu::models.spa_middleware'))->group(function () {
        Route::any('spa/{model}/{action?}/{id?}', Master::class)->name('lu::spa.resources');
    });
}
