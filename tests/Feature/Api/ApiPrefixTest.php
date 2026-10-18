<?php

namespace All1\LuModels\Tests\Feature\Api;

use All1\LuModels\Tests\TestCase;
use App\Models\Post;
use App\Models\User;

/** `lu::models.api_prefix` sits between /api and the model, as its comment always said. */
class ApiPrefixTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('lu::models.api_prefix', 'v1');
    }

    public function test_the_prefix_is_honoured_by_both_route_shapes(): void
    {
        $user = User::create(['name' => 'Neo', 'email' => 'neo@example.test']);
        Post::create(['user_id' => $user->id, 'title' => 'Alpha']);

        $this->assertSame(url('/api/v1/posts'), route('lu::api.index', ['model' => 'posts']));
        $this->assertSame(url('/api/v1/posts/index'), route('lu::api.resources', ['model' => 'posts', 'action' => 'index']));

        $this->actingAs($user)->getJson('/api/v1/posts')->assertOk()->assertJsonPath('data.0.title', 'Alpha');
        $this->actingAs($user)->getJson('/api/posts')->assertNotFound();
    }
}
