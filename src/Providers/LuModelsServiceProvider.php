<?php

namespace All1\LuModels\Providers;

use All1\LuModels\Services\LuModels;
use Illuminate\Support\Facades\Blade;
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

        $this->loadRoutesFrom(__DIR__.'/../../routes/lu_models-routes.php');
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
