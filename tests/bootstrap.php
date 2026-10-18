<?php

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/*
 * Bootstrap for the class-level suite (tests/Unit), which runs the package code without booting a
 * Laravel application. The framework's helpers come from vendor/autoload.php; what no app provides
 * here are the facade aliases the source refers to (`use Str;`, `Route::`, `App::`, `Log::`).
 */

require __DIR__.'/../vendor/autoload.php';

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
