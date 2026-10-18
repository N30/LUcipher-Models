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
    public static function register(string $uri, string $controller, string $name, bool $actionOptional = true, ?Router $router = null): void
    {
        $router ??= Route::getFacadeRoot();
        $action = $actionOptional ? '{action?}' : '{action}';
        $path = rtrim($uri, '/').'/{model}/'.$action.'/{id?}';

        $router->get($path, $controller)
            ->where('action', implode('|', self::actionsFor('GET')))
            ->name($name);
        $router->post($path, $controller)
            ->where('action', implode('|', self::actionsFor('POST')))
            ->name($name.'.store');
        $router->match(['PUT', 'PATCH'], $path, $controller)
            ->where('action', implode('|', self::actionsFor('PUT')))
            ->name($name.'.update');
        $router->delete($path, $controller)
            ->where('action', implode('|', self::actionsFor('DELETE')))
            ->name($name.'.destroy');
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
