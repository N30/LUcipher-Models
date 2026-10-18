<?php

/*
 * Test bootstrap for class-level tests.
 *
 * The package runs inside a Laravel app, which supplies the global helpers (event(), request(),
 * config(), abort()), the facade aliases (Str, Route, App, Log) and the optional dependencies the
 * Actions and Verbs extend (lorisleiva/laravel-actions, hirethunk/verbs). The suite does not pull
 * in the full framework, so this file provides minimal stand-ins — only when the real thing is
 * not already loaded, and only for the test run. Nothing here ships in src/.
 */

namespace {
    require __DIR__.'/../vendor/autoload.php';
}

namespace Thunk\Verbs {
    if (! class_exists(Event::class)) {
        abstract class Event {}
    }
}

namespace Lorisleiva\Actions\Concerns {
    if (! trait_exists(AsAction::class)) {
        trait AsAction
        {
            public static function run(...$arguments)
            {
                return (new static)->handle(...$arguments);
            }
        }
    }
}

namespace Illuminate\Foundation\Events {
    if (! trait_exists(Dispatchable::class)) {
        trait Dispatchable {}
    }
}

namespace Illuminate\Queue {
    if (! trait_exists(SerializesModels::class)) {
        trait SerializesModels {}
    }
}

namespace Illuminate\Broadcasting {
    if (! trait_exists(InteractsWithSockets::class)) {
        trait InteractsWithSockets {}
    }
}

namespace {
    use Illuminate\Container\Container;
    use Illuminate\Support\Facades\App;
    use Illuminate\Support\Facades\Log;
    use Illuminate\Support\Facades\Route;
    use Illuminate\Support\Str;
    use Symfony\Component\HttpKernel\Exception\HttpException;
    use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

    if (! function_exists('event')) {
        function event(...$args)
        {
            return Container::getInstance()->make('events')->dispatch(...$args);
        }
    }

    if (! function_exists('request')) {
        function request($key = null, $default = null)
        {
            $request = Container::getInstance()->make('request');

            return $key === null ? $request : $request->input($key, $default);
        }
    }

    if (! function_exists('config')) {
        function config($key = null, $default = null)
        {
            $config = Container::getInstance()->make('config');
            if ($key === null) {
                return $config;
            }
            if (is_array($key)) {
                $config->set($key);

                return null;
            }

            return $config->get($key, $default);
        }
    }

    if (! function_exists('abort')) {
        function abort($code, $message = '', array $headers = [])
        {
            if ($code == 404) {
                throw new NotFoundHttpException($message, null, 0, $headers);
            }
            throw new HttpException($code, $message, null, $headers);
        }
    }

    if (! function_exists('session')) {
        function session($key = null, $default = null)
        {
            $session = Container::getInstance()->make('session');

            return $key === null ? $session : $session->get($key, $default);
        }
    }

    if (! function_exists('redirect')) {
        function redirect($to = null, $status = 302, $headers = [], $secure = null)
        {
            $redirector = Container::getInstance()->make('redirect');

            return $to === null ? $redirector : $redirector->to($to, $status, $headers, $secure);
        }
    }

    if (! function_exists('back')) {
        function back($status = 302, $headers = [], $fallback = false)
        {
            return Container::getInstance()->make('redirect')->back($status, $headers, $fallback);
        }
    }

    if (! function_exists('auth')) {
        function auth($guard = null)
        {
            return Container::getInstance()->make('auth');
        }
    }

    foreach ([
        'Str' => Str::class,
        'Route' => Route::class,
        'App' => App::class,
        'Log' => Log::class,
    ] as $alias => $class) {
        if (! class_exists($alias, false)) {
            class_alias($class, $alias);
        }
    }
}
