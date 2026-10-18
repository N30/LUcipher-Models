<?php

namespace All1\LuModels\Tests\Feature\Security;

use All1\LuModels\Tests\TestCase;
use App\Models\Post;
use App\Models\User;

/** Phase 3 §3: a client key the model does not allow written is rejected, on every transport. */
class MassAssignmentTest extends TestCase
{
    protected User $user;

    protected Post $post;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::create(['name' => 'Neo', 'email' => 'neo@example.test']);
        $this->post = Post::create(['user_id' => $this->user->id, 'title' => 'Old']);
    }

    private function url(string $transport, string $action, $id = null): string
    {
        return route("lu::{$transport}.resources", array_filter(['model' => 'posts', 'action' => $action, 'id' => $id]));
    }

    public function test_api_update_with_a_guarded_key_is_422_and_names_the_key(): void
    {
        $this->actingAs($this->user)
            ->putJson($this->url('api', 'update', $this->post->id), ['title' => 'New', 'secret_token' => 'x'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['secret_token']);

        $this->assertSame('Old', $this->post->refresh()->title);
        $this->assertNull($this->post->secret_token);
    }

    public function test_api_store_with_a_guarded_key_is_422_and_creates_nothing(): void
    {
        $this->actingAs($this->user)
            ->postJson($this->url('api', 'store'), ['title' => 'Fresh', 'secret_token' => 'x'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['secret_token']);

        $this->assertDatabaseCount('posts', 1);
    }

    public function test_api_store_and_update_with_allowed_keys_succeed(): void
    {
        $this->actingAs($this->user)
            ->postJson($this->url('api', 'store'), ['title' => 'Fresh', 'body' => 'text'])
            ->assertOk()
            ->assertJsonPath('message', 'We have received and saved your information successfully.');

        $this->actingAs($this->user)
            ->putJson($this->url('api', 'update', $this->post->id), ['title' => 'New'])
            ->assertOk()
            ->assertJsonPath('data.title', 'New');
    }

    public function test_api_update_of_a_missing_record_is_404(): void
    {
        $this->actingAs($this->user)
            ->putJson($this->url('api', 'update', 999), ['title' => 'New'])
            ->assertNotFound();
    }

    public function test_rest_update_with_a_guarded_key_redirects_back_with_the_error(): void
    {
        $this->actingAs($this->user)
            ->from('/somewhere')
            ->put($this->url('rest', 'update', $this->post->id), ['title' => 'New', 'secret_token' => 'x'])
            ->assertRedirect('/somewhere')
            ->assertSessionHasErrors('secret_token');

        $this->assertSame('Old', $this->post->refresh()->title);
    }
}
