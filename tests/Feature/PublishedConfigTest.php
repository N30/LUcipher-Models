<?php

namespace All1\LuModels\Tests\Feature;

use All1\LuModels\Tests\TestCase;

/** A published config/lu/models.php (loaded by Laravel as "lu.models") wins over the package defaults. */
class PublishedConfigTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('lu.models', ['after_store_redirect' => 'index']);
    }

    public function test_published_values_override_defaults_and_untouched_keys_keep_theirs(): void
    {
        $this->assertSame('index', config('lu::models.after_store_redirect'));
        $this->assertSame('show', config('lu::models.after_update_redirect'));
        $this->assertTrue(config('lu::models.enable_web_routes'), 'values set after publishing still apply');
    }
}
