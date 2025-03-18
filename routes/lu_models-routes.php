<?php

// use All1\LuModels\Http\Controllers\LuModelsController;

// Route::get('/lu_models', [LuModelsController::class, 'index'])->name('lu_models.index');
// Route::get('/lu_models/create', [LuModelsController::class, 'create'])->name('lu_models.create');
// Route::post('/lu_models', [LuModelsController::class, 'store'])->name('lu_models.store');
// Route::get('/lu_models/{lu_model}', [LuModelsController::class, 'show'])->name('lu_models.show');
// Route::get('/lu_models/{lu_model}/edit', [LuModelsController::class, 'edit'])->name('lu_models.edit');
// Route::put('/lu_models/{lu_model}', [LuModelsController::class, 'update'])->name('lu_models.update');
// Route::delete('/lu_models/{lu_model}', [LuModelsController::class, 'destroy'])->name('lu_models.destroy');

//Route::middleware(['auth'])->group(function () {
if(config('lu::models.enable_web_routes')) {
    Route::middleware(config('lu::models.web_middleware'))->group(function () {
        Route::any('web/{model}/{action?}/{id?}', \All1\LuModels\Http\Controllers\ResourceController::class)->name('lu::web.resources');
    });
}
if(config('lu::models.enable_rest_routes')) {
Route::middleware(['auth',/***** enforced as REST is just for developers and admin ******/'web'])->group(function () {
    Route::any('rest/{model}/{action?}/{id?}', \All1\LuModels\Http\Controllers\Api\ApiRestController::class)->name('lu::rest.resources');
});
}
if(config('lu::models.enable_api_routes')) {
    if (array_key_exists('auth:sanctum', App::make(\Illuminate\Routing\Router::class)->getMiddleware())) {
        Route::prefix('api')->middleware('api')->group( ['middleware' => 'auth:sanctum'], function() {
            
                Route::any('{model}/{action}/{id?}', \All1\LuModels\Http\Controllers\Api\ApiResourceController::class)->name('lu::api.resources');
        
        });
    } else {
        Route::prefix('api')->middleware('api')->group( function() {
            \Log::warning('auth:sanctum middleware not found useing unsecure API');
            Route::any('{model}/{action}/{id?}', \All1\LuModels\Http\Controllers\Api\ApiResourceController::class)->name('lu::api.resources');
        });
    }
}
if(config('lu::models.enable_spa_routes')) {
//Route::middleware(['auth'])->group(function () {
    Route::middleware(config('lu::models.spa_middleware'))->group(function () {
        Route::any('spa/{model}/{action?}/{id?}', \All1\LuModels\Livewire\Master::class)->name('lu::spa.resources');
    });
}