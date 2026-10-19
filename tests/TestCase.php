<?php

namespace All1\LuModels\Tests;

use All1\LuModels\Providers\LuModelsServiceProvider;
use App\Models\Post;
use App\Models\User;
use Flux\FluxServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Mcp\Server\McpServiceProvider;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    /**
     * The package, plus the optional packages it integrates with when they are installed (Livewire for
     * /spa, Flux for the optional skin, laravel/mcp for the MCP transport). Registered by name rather
     * than discovered: Testbench's package discovery proved environment-dependent in CI.
     */
    protected function getPackageProviders($app): array
    {
        return array_values(array_filter([
            LuModelsServiceProvider::class,
            LivewireServiceProvider::class,
            FluxServiceProvider::class,
            McpServiceProvider::class,
        ], 'class_exists'));
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
        // pinned, so a skeleton .env left behind by `vendor/bin/testbench` (CACHE_STORE=database …) cannot reach the suite
        $app['config']->set('cache.default', 'array');
        $app['config']->set('session.driver', 'array');
        $app['config']->set('queue.default', 'sync');

        foreach (['web', 'rest', 'api', 'spa', 'mcp'] as $transport) {
            $app['config']->set("lu::models.enable_{$transport}_routes", true);
        }
        $app['config']->set('lu::models.models_path', __DIR__.'/Fixtures/app/Models');
        $app['config']->set('lu::models.models', [Post::class => true]);
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
