<?php

namespace All1\LuModels\Routing;

use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;

/**
 * The dynamic {model}/{action}/{id?} resource routes, scoped by HTTP method.
 *
 * 3.0.x registered one Route::any per transport, so the action came only from the path and
 * GET /web/users/destroy/5 deleted the row (CSRF only guards non-GET requests). Each action
 * is now reachable only through the method a Laravel resource route would use for it.
 */
final class ResourceRoutes
{
    /** action => HTTP methods that may reach it (HEAD rides along with GET). */
    public const ACTION_METHODS = [
        'index' => ['GET', 'HEAD'],
        'create' => ['GET', 'HEAD'],
        'show' => ['GET', 'HEAD'],
        'edit' => ['GET', 'HEAD'],
        'store' => ['POST'],
        'update' => ['PUT', 'PATCH'],
        'destroy' => ['DELETE'],
    ];

    /**
     * Register the method-scoped routes for one transport.
     *
     * The GET route keeps the transport's historical name (e.g. "lu::web.resources") so every
     * existing route('lu::web.resources', [...]) call keeps generating the same URL; the write
     * routes are named "{name}.store", "{name}.update" and "{name}.destroy". All of them share
     * the same URI shape, and controllers read the transport from the segment after "lu::".
     */
    /** @param  list<string>  $writeMiddleware  middleware for the store/update/destroy routes only (e.g. a write rate limit) */
    public static function register(string $uri, string $controller, string $name, bool $actionOptional = true, ?Router $router = null, array $writeMiddleware = []): void
    {
        $router ??= Route::getFacadeRoot();
        $action = $actionOptional ? '{action?}' : '{action}';
        $path = rtrim($uri, '/').'/{model}/'.$action.'/{id?}';

        $router->get($path, $controller)
            ->where('action', implode('|', self::actionsFor('GET')))
            ->name($name);
        $router->post($path, $controller)
            ->where('action', implode('|', self::actionsFor('POST')))
            ->middleware($writeMiddleware)
            ->name($name.'.store');
        $router->match(['PUT', 'PATCH'], $path, $controller)
            ->where('action', implode('|', self::actionsFor('PUT')))
            ->middleware($writeMiddleware)
            ->name($name.'.update');
        $router->delete($path, $controller)
            ->where('action', implode('|', self::actionsFor('DELETE')))
            ->middleware($writeMiddleware)
            ->name($name.'.destroy');
    }

    /**
     * The REST-shaped routes of one transport: GET {uri}/{model} (index), POST {uri}/{model} (store),
     * GET, PUT|PATCH and DELETE {uri}/{model}/{id} (show, update, destroy). The action travels as a
     * route default, so the controllers read it exactly as they read it from the action-shaped
     * routes. Names: {name}.index, .store, .show, .update, .destroy.
     *
     * @param  list<string>  $writeMiddleware  middleware for the store/update/destroy routes only
     */
    public static function registerRest(string $uri, string $controller, string $name, ?Router $router = null, array $writeMiddleware = []): void
    {
        $router ??= Route::getFacadeRoot();
        $collection = rtrim($uri, '/').'/{model}';
        $member = $collection.'/{id}';

        $router->get($collection, $controller)->defaults('action', 'index')->name($name.'.index');
        $router->post($collection, $controller)->defaults('action', 'store')->middleware($writeMiddleware)->name($name.'.store');
        // the id slot never takes an action name, so /{model}/store stays the action-shaped route's (or nothing)
        $notAnAction = '(?!(?:'.implode('|', array_keys(self::ACTION_METHODS)).')$)[^/]+';
        $router->get($member, $controller)->where('id', $notAnAction)->defaults('action', 'show')->name($name.'.show');
        $router->match(['PUT', 'PATCH'], $member, $controller)->where('id', $notAnAction)->defaults('action', 'update')->middleware($writeMiddleware)->name($name.'.update');
        $router->delete($member, $controller)->where('id', $notAnAction)->defaults('action', 'destroy')->middleware($writeMiddleware)->name($name.'.destroy');
    }

    /** The URI prefix of the api transport: `api`, or `api/{lu::models.api_prefix}`. */
    public static function apiPrefix(): string
    {
        return trim('api/'.trim((string) config('lu::models.api_prefix'), '/'), '/');
    }

    /** Whether $method may reach $action. Unknown actions are never allowed. */
    public static function allows(string $method, ?string $action): bool
    {
        $action = $action === null || $action === '' ? 'index' : $action;

        return in_array(strtoupper($method), self::ACTION_METHODS[$action] ?? [], true);
    }

    /** @return list<string> the actions $method may reach */
    public static function actionsFor(string $method): array
    {
        $method = strtoupper($method);

        return array_keys(array_filter(
            self::ACTION_METHODS,
            fn (array $methods) => in_array($method, $methods, true)
        ));
    }
}
