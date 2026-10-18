<?php

namespace All1\LuModels\Tests\Feature;

use All1\LuModels\Providers\LuModelsServiceProvider;
use All1\LuModels\Services\LuModels;
use All1\LuModels\Tests\TestCase;
use All1\LuUnite\Providers\LuUniteServiceProvider;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;

class ProviderBootsTest extends TestCase
{
    public function test_the_provider_registers_config_views_and_routes(): void
    {
        $this->assertSame('show', config('lu::models.after_store_redirect'), 'package config is merged under lu::models');
        $this->assertTrue(view()->exists('lu_models::web.index'));
        $this->assertTrue(view()->exists('lu_models::index-table'), 'component views are reachable under the same namespace');
        $this->assertTrue(Route::has('lu::web.resources'));
        $this->assertTrue(Route::has('lu::web.resources.destroy'));
        $this->assertTrue(Route::has('lu::rest.resources'));
        $this->assertTrue(Route::has('lu::api.resources'));
        $this->assertTrue(Route::has('lu::spa.resources'));
    }

    public function test_the_service_is_a_singleton_with_its_historical_alias(): void
    {
        $service = $this->app->make(LuModels::class);

        $this->assertSame($service, $this->app->make(LuModels::class));
        $this->assertSame($service, $this->app->make('LuModels'));
    }

    public function test_class_based_components_resolve_under_the_lu_models_alias(): void
    {
        $html = Blade::render('<x-lu_models::dynamic-layout layout="fixture::layouts.app">rendered body</x-lu_models::dynamic-layout>');

        $this->assertStringContainsString('rendered body', $html);
        $this->assertStringContainsString('lu_models fixture', $html, 'the layout the component was given is the one extended');
    }

    public function test_booting_the_package_needs_no_unite_and_touches_no_environment_variable(): void
    {
        $this->assertFalse(class_exists(LuUniteServiceProvider::class), 'lucipher/unite is not installed for this suite');
        $this->assertFalse(defined('LU_PATH'));

        foreach (['SESSION_DOMAIN', 'APP_CONFIG_CACHE', 'TWILIO_FROM', 'MAIL_FROM_NAME'] as $name) {
            $this->assertFalse(getenv($name), "$name must not be set by the package");
        }
    }

    public function test_enabling_the_spa_transport_without_livewire_fails_with_a_clear_message(): void
    {
        LuModelsServiceProvider::assertLivewireInstalled();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('livewire/livewire is not installed');
        LuModelsServiceProvider::assertLivewireInstalled('Vendor\\Missing\\Livewire');
    }
}
