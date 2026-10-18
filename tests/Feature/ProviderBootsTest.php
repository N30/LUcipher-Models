<?php

namespace All1\LuModels\Tests\Feature;

use All1\LuModels\Tests\TestCase;
use Illuminate\Support\Facades\Route;

class ProviderBootsTest extends TestCase
{
    public function test_the_provider_registers_config_views_and_routes(): void
    {
        $this->assertSame('show', config('lu::models.after_store_redirect'), 'package config is merged under lu::models');
        $this->assertTrue(view()->exists('lu_models::web.index'));
        $this->assertTrue(Route::has('lu::web.resources'));
        $this->assertTrue(Route::has('lu::rest.resources'));
        $this->assertTrue(Route::has('lu::spa.resources'));
    }
}
