<?php

namespace All1\LuModels\Tests\Feature\Security;

use All1\LuModels\Tests\TestCase;
use App\Models\User;

/**
 * Phase 3 §7: the browser transports verify CSRF tokens; the API does not need them.
 * Laravel skips token checks while the app environment is "testing", so this class runs as "local".
 */
class CsrfTest extends TestCase
{
    protected User $user;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['env'] = 'local';
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::create(['name' => 'Neo', 'email' => 'neo@example.test']);
    }

    public function test_web_writes_without_a_token_are_419(): void
    {
        $this->actingAs($this->user)->post(route('lu::web.resources', ['model' => 'posts', 'action' => 'store']), ['title' => 'x'])->assertStatus(419);
        $this->assertDatabaseCount('posts', 0);
    }

    public function test_web_writes_with_a_token_go_through(): void
    {
        $this->actingAs($this->user)->get(route('lu::web.resources', ['model' => 'posts', 'action' => 'index']))->assertOk();

        $this->actingAs($this->user)
            ->post(route('lu::web.resources', ['model' => 'posts', 'action' => 'store']), ['_token' => csrf_token(), 'title' => 'x'])
            ->assertRedirect();

        $this->assertDatabaseCount('posts', 1);
    }

    public function test_rest_writes_without_a_token_are_419(): void
    {
        $this->actingAs($this->user)->post(route('lu::rest.resources', ['model' => 'posts', 'action' => 'store']), ['title' => 'x'])->assertStatus(419);
    }

    public function test_the_api_transport_has_no_csrf_for_an_authenticated_caller(): void
    {
        $this->actingAs($this->user)
            ->postJson(route('lu::api.resources', ['model' => 'posts', 'action' => 'store']), ['title' => 'x'])
            ->assertOk();

        $this->assertDatabaseCount('posts', 1);
    }

    public function test_the_api_transport_relies_on_authentication_instead(): void
    {
        $this->postJson(route('lu::api.resources', ['model' => 'posts', 'action' => 'store']), ['title' => 'y'])
            ->assertForbidden();

        $this->assertDatabaseCount('posts', 0);
    }
}
