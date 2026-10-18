<?php

namespace All1\LuModels\Providers;

use All1\LuModels\Services\LuModels;
use All1\LuModels\Support\Exposure;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

/**
 * Registers everything the package ships from its own directory: config, views, Blade components,
 * the LuModels service and the resource routes. It needs nothing from lucipher/unite and sets no
 * environment variable, so the package installs from Packagist or any VCS repository.
 */
class LuModelsServiceProvider extends ServiceProvider
{
    public const CONFIG_KEY = 'lu::models';

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/lu/models.php', self::CONFIG_KEY);

        $this->mergePublishedConfig();

        $this->app->singleton(LuModels::class, fn () => new LuModels);
        $this->app->alias(LuModels::class, 'LuModels');
    }

    public function boot(): void
    {
        // Config set between register() and boot() (Testbench does this; so may another provider)
        // must not hide the app's published file: fold it once more. The operation is idempotent.
        $this->mergePublishedConfig();

        $this->publishes([
            __DIR__.'/../../config/lu/models.php' => config_path('lu/models.php'),
        ], 'lu-models-config');

        $this->publishes([
            __DIR__.'/../../resources/views' => resource_path('views/vendor/lu_models'),
        ], 'lu-models-views');

        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'lu_models');
        $this->loadViewsFrom(__DIR__.'/../../resources/views/components', 'lu_models');

        Blade::componentNamespace('All1\\LuModels\\View\\Components', 'lu_models');

        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        $this->publishesMigrations([
            __DIR__.'/../../database/migrations' => database_path('migrations'),
        ], 'lu-models-migrations');

        $this->registerRateLimiters();

        $this->loadRoutesFrom(__DIR__.'/../../routes/lu_models-routes.php');
    }

    /**
     * One named limiter per transport plus one for writes, read from lu::models.rate_limits at
     * request time (so a published config or a runtime change applies without a reboot). The key
     * is the signed-in user, or the IP for guests.
     */
    protected function registerRateLimiters(): void
    {
        foreach ([...Exposure::TRANSPORTS, 'writes'] as $name) {
            RateLimiter::for('lu-models-'.$name, function (Request $request) use ($name) {
                $perMinute = config('lu::models.rate_limits.'.$name);
                if ($perMinute === null || $perMinute === false) {
                    return Limit::none();
                }

                return Limit::perMinute((int) $perMinute)->by($name.'|'.($request->user()?->getAuthIdentifier() ?? 'guest:'.$request->ip()));
            });
        }
    }

    /**
     * Laravel loads a published config/lu/models.php under "lu.models"; fold it over the package
     * defaults so config('lu::models.*') keeps seeing the app's overrides, as it always has.
     */
    protected function mergePublishedConfig(): void
    {
        $published = $this->app['config']->get('lu.models');
        if (is_array($published)) {
            $this->app['config']->set(self::CONFIG_KEY, array_replace(
                $this->app['config']->get(self::CONFIG_KEY, []),
                $published,
            ));
        }
    }

    /**
     * The /spa transport mounts a Livewire component. Livewire is a suggested dependency, so an app
     * that enables the transport without it gets this message instead of a class-not-found.
     *
     * @throws \LogicException
     */
    public static function assertLivewireInstalled(string $class = Livewire::class): void
    {
        if (! class_exists($class)) {
            throw new \LogicException('lu_models: lu::models.enable_spa_routes is on but livewire/livewire is not installed. Run `composer require livewire/livewire` or turn the SPA transport off.');
        }
    }
}
