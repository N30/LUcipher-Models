<?php

namespace All1\LuModels\Tests;

use All1\LuModels\Providers\LuModelsServiceProvider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [LuModelsServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        $app['config']->set('auth.providers.users.model', User::class);

        foreach (['web', 'rest', 'api', 'spa'] as $transport) {
            $app['config']->set("lu::models.enable_{$transport}_routes", true);
        }
        $app['config']->set('lu::models.web_middleware', ['web']);
        $app['config']->set('lu::models.spa_middleware', ['web']);
        $app['config']->set('lu::models.allow_unauthenticated_api', true);
        $app['config']->set('lu::models.layout', 'fixture::layouts.app');
        $app['view']->addNamespace('fixture', __DIR__.'/Fixtures/resources/views');
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Fixtures/database/migrations');
    }
}
