<?php

namespace All1\LuModels\Tests\Feature;

use All1\LuModels\Tests\TestCase;
use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Facades\Event;

/**
 * The /web transport end to end: regression tests for the live bugs the study (§3) listed and
 * fix/3.0.1-security closed. Every test states the behaviour the package must keep.
 */
class WebTransportTest extends TestCase
{
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::create(['name' => 'Neo', 'email' => 'neo@example.test']);
    }

    protected function url(string $action, $id = null): string
    {
        return route('lu::web.resources', array_filter(['model' => 'posts', 'action' => $action, 'id' => $id]));
    }

    public function test_index_renders_on_an_empty_table(): void
    {
        $this->actingAs($this->user)->get($this->url('index'))->assertOk();
    }

    public function test_index_renders_with_rows(): void
    {
        Post::create(['user_id' => $this->user->id, 'title' => 'Hello']);

        $this->actingAs($this->user)->get($this->url('index'))->assertOk()->assertSee('Hello');
    }

    public function test_a_get_request_cannot_destroy_a_row(): void
    {
        $post = Post::create(['user_id' => $this->user->id, 'title' => 'Keep me']);

        $this->actingAs($this->user)->get($this->url('destroy', $post->id));

        $this->assertDatabaseHas('posts', ['id' => $post->id]);
    }

    public function test_delete_removes_the_row(): void
    {
        $post = Post::create(['user_id' => $this->user->id, 'title' => 'Bye']);

        $this->actingAs($this->user)->delete($this->url('destroy', $post->id))->assertRedirect();

        $this->assertDatabaseMissing('posts', ['id' => $post->id]);
    }

    public function test_update_respects_fillable(): void
    {
        $post = Post::create(['user_id' => $this->user->id, 'title' => 'Old']);

        $this->actingAs($this->user)->put($this->url('update', $post->id), [
            'title' => 'New',
            'secret_token' => 'injected',
        ]);

        $post->refresh();
        $this->assertSame('New', $post->title);
        $this->assertNull($post->secret_token, 'secret_token is not fillable and must not be mass-assigned');
    }

    public function test_store_fires_the_created_event_not_updated(): void
    {
        Event::fake(['post.created', 'post.updated', 'post.deleted']);

        $this->actingAs($this->user)->post($this->url('store'), ['title' => 'Fresh']);

        Event::assertDispatched('post.created');
        Event::assertNotDispatched('post.updated');
    }

    public function test_destroy_fires_the_deleted_event(): void
    {
        $post = Post::create(['user_id' => $this->user->id, 'title' => 'Gone']);
        Event::fake(['post.created', 'post.updated', 'post.deleted']);

        $this->actingAs($this->user)->delete($this->url('destroy', $post->id));

        Event::assertDispatched('post.deleted');
    }
}
