<?php

namespace All1\LuModels\Tests\Feature;

use All1\LuModels\Models\LuModel;
use All1\LuModels\Scopes\UserOwnershipScope;
use All1\LuModels\Tests\TestCase;
use App\Models\Memo;
use App\Models\Post;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/** A transport that is off has no routes to describe, and a console run is not scoped by ownership. */
class RoutesOffTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('lu::models.enable_rest_routes', false);
    }

    public function test_meta_data_skips_the_routes_of_a_transport_that_is_off(): void
    {
        $routes = LuModel::getMetaData(Post::class)['routes'];

        $this->assertArrayHasKey('web', $routes);
        $this->assertArrayNotHasKey('rest', $routes);
        $this->assertFalse(Route::has('lu::rest.resources'));
    }

    public function test_the_ownership_scope_stands_down_for_a_console_run_that_is_not_a_test(): void
    {
        DB::table('memos')->insert(['user_id' => 1, 'body' => 'cron job data', 'created_at' => now(), 'updated_at' => now()]);

        $this->assertSame(0, Memo::count(), 'a guest sees nothing');
        $this->assertSame(1, Memo::withoutGlobalScope(UserOwnershipScope::class)->count());

        $env = $this->app['env'];
        $this->app->instance('env', 'local'); // runningInConsole() is true under PHPUnit; this makes runningUnitTests() false
        try {
            $this->assertSame(1, Memo::count(), 'a console command (a job, a seeder) sees every row');
        } finally {
            $this->app->instance('env', $env);
        }
    }
}
