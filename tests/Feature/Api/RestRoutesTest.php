<?php

namespace All1\LuModels\Tests\Feature\Api;

use All1\LuModels\Tests\TestCase;
use App\Models\Post;
use App\Models\User;

/** Phase 5 §1: the REST-shaped routes are the documented API; the action-shaped ones still answer. */
class RestRoutesTest extends TestCase
{
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::create(['name' => 'Neo', 'email' => 'neo@example.test']);
        Post::create(['user_id' => $this->user->id, 'title' => 'Alpha', 'body' => 'a']);
    }

    public function test_the_rest_shaped_routes_are_named_and_shaped_like_a_resource(): void
    {
        $this->assertSame(url('/api/posts'), route('lu::api.index', ['model' => 'posts']));
        $this->assertSame(url('/api/posts'), route('lu::api.store', ['model' => 'posts']));
        $this->assertSame(url('/api/posts/7'), route('lu::api.show', ['model' => 'posts', 'id' => 7]));
        $this->assertSame(url('/api/posts/7'), route('lu::api.update', ['model' => 'posts', 'id' => 7]));
        $this->assertSame(url('/api/posts/7'), route('lu::api.destroy', ['model' => 'posts', 'id' => 7]));
    }

    public function test_a_full_round_trip_over_the_rest_shaped_routes(): void
    {
        $as = $this->actingAs($this->user);

        $as->getJson('/api/posts')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.title', 'Alpha')->assertJsonStructure(['data', 'meta', 'links']);

        $created = $as->postJson('/api/posts', ['title' => 'Beta'])->assertOk()->assertJsonPath('data.title', 'Beta')->json('data.id');

        $as->getJson('/api/posts/'.$created)->assertOk()->assertJsonPath('data.title', 'Beta')->assertJsonMissingPath('data.secret_token');
        $as->patchJson('/api/posts/'.$created, ['title' => 'Beta 2'])->assertOk()->assertJsonPath('data.title', 'Beta 2');
        $as->putJson('/api/posts/'.$created, ['title' => 'Beta 3'])->assertOk();
        $this->assertSame('Beta 3', Post::find($created)->title);

        $as->deleteJson('/api/posts/'.$created)->assertOk()->assertJsonPath('message', 'Record deleted');
        $as->getJson('/api/posts/'.$created)->assertNotFound();
        $this->assertNull(Post::find($created));
    }

    public function test_the_action_shaped_routes_still_answer_with_the_same_envelope(): void
    {
        $post = Post::first();
        $as = $this->actingAs($this->user);

        $as->getJson('/api/posts/index')->assertOk()->assertJsonPath('data.0.title', 'Alpha');
        $as->getJson('/api/posts/show/'.encrypt($post->id))->assertOk()->assertJsonPath('data.title', 'Alpha');
    }

    public function test_the_wrong_method_is_refused_and_an_unexposed_model_is_a_404(): void
    {
        $post = Post::first();
        $as = $this->actingAs($this->user);

        $as->postJson('/api/posts/'.$post->id, ['title' => 'x'])->assertStatus(405);
        $as->deleteJson('/api/posts')->assertStatus(405);
        $as->getJson('/api/memos')->assertNotFound();
        $as->getJson('/api/nothings')->assertNotFound();
    }

    public function test_plain_encrypted_and_unknown_keys_on_the_member_route(): void
    {
        $post = Post::first();
        $other = User::create(['name' => 'Other', 'email' => 'other@example.test']);

        // a guest first: actingAs() keeps the user for the rest of the test
        $this->getJson('/api/posts/'.$post->id)->assertForbidden()->assertJsonPath('message', 'Sign in, or use the encrypted id, to reach this record through this URL.');
        $this->actingAs($this->user)->getJson('/api/posts/'.encrypt($post->id))->assertOk()->assertJsonPath('data.title', 'Alpha');
        $this->actingAs($this->user)->getJson('/api/posts/'.$post->id)->assertOk()->assertJsonPath('data.title', 'Alpha', 'a signed-in caller may use the plain key; the policy decides');
        $this->actingAs($other)->getJson('/api/posts/'.$post->id)->assertForbidden();
        $this->actingAs($this->user)->getJson('/api/posts/not-a-key')->assertNotFound();

        $this->actingAs($this->user)->patchJson('/api/posts/'.encrypt($post->id), ['title' => 'Via key'])->assertOk();
        $this->assertSame('Via key', $post->fresh()->title);
    }

    public function test_a_guest_is_refused_where_the_policy_requires_a_user(): void
    {
        $post = Post::first();

        $this->getJson('/api/posts')->assertForbidden();
        $this->patchJson('/api/posts/'.$post->id, ['title' => 'x'])->assertForbidden();
        $this->assertSame('Alpha', $post->fresh()->title);
    }
}
