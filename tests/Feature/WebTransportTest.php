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

    public function test_the_index_shows_the_models_index_columns_and_nothing_hidden(): void
    {
        $post = Post::create(['user_id' => $this->user->id, 'title' => 'Hello', 'body' => 'the body']);
        $post->forceFill(['secret_token' => 'TOKEN'])->save();

        $html = $this->actingAs($this->user)->get($this->url('index'))->assertOk()->getContent();

        $this->assertStringContainsString('>Title<', strip_tags($html, '<a><th>') === $html ? $html : $html, 'the Title header');
        $this->assertStringContainsString('Created At', $html);
        $this->assertStringNotContainsString('User Id', $html, 'index_columns names id, title and created_at only');
        $this->assertStringNotContainsString('the body', $html, 'body is not readable on the index');
        $this->assertStringNotContainsString('TOKEN', $html);
        $this->assertStringContainsString(route('lu::web.resources', ['posts', 'edit', $post->id]), $html, 'the row links to its edit page by plain key');
        $this->assertStringContainsString('sort=title', $html, 'the headers sort');
    }

    public function test_the_show_page_renders_what_show_may_read(): void
    {
        $post = Post::create(['user_id' => $this->user->id, 'title' => 'Hello', 'body' => 'the body']);
        $post->forceFill(['secret_token' => 'TOKEN'])->save();

        $html = $this->actingAs($this->user)->get($this->url('show', $post->id))->assertOk()->getContent();

        $this->assertStringContainsString('the body', $html);
        $this->assertStringContainsString('Hello', $html);
        $this->assertStringNotContainsString('TOKEN', $html);
        $this->assertStringNotContainsString('Secret Token', $html);
    }

    public function test_the_forms_come_from_the_operations_schema(): void
    {
        $html = $this->actingAs($this->user)->get($this->url('create'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<input[^>]*name="title"[^>]*required/', $html);
        $this->assertMatchesRegularExpression('/<input[^>]*name="title"[^>]*maxlength="120"/', $html, 'from the max:120 rule');
        $this->assertStringContainsString('Shown as the headline', $html, 'help text from $lu_help');
        $this->assertMatchesRegularExpression('/<textarea[^>]*name="body"/', $html);
        $this->assertStringNotContainsString('name="user_id"', $html, 'injected by the package');
        $this->assertStringNotContainsString('name="secret_token"', $html);
        $this->assertStringNotContainsString('name="id"', $html);

        $post = Post::create(['user_id' => $this->user->id, 'title' => 'Old title', 'body' => 'old body']);
        $html = $this->actingAs($this->user)->get($this->url('edit', $post->id))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<input[^>]*name="title"[^>]*value="Old title"/', $html);
        $this->assertStringContainsString('old body</textarea>', $html);
        $this->assertStringContainsString(route('lu::web.resources.update', ['posts', 'update', $post->id]), $html);
    }

    public function test_the_toast_shows_once(): void
    {
        $response = $this->actingAs($this->user)->post($this->url('store'), ['title' => 'Fresh']);
        $response->assertRedirect();

        $this->actingAs($this->user)->get($response->headers->get('Location'))->assertOk()->assertSee('saved your information');
        $this->actingAs($this->user)->get($this->url('index'))->assertOk()->assertDontSee('saved your information');
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

    public function test_update_rejects_a_guarded_key_and_writes_nothing(): void
    {
        $post = Post::create(['user_id' => $this->user->id, 'title' => 'Old']);

        $this->actingAs($this->user)->put($this->url('update', $post->id), [
            'title' => 'New',
            'secret_token' => 'injected',
        ])->assertRedirect()->assertSessionHasErrors('secret_token');

        $post->refresh();
        $this->assertSame('Old', $post->title, 'a write naming a guarded key is rejected whole');
        $this->assertNull($post->secret_token, 'secret_token is not fillable and must not be mass-assigned');
    }

    public function test_update_with_fillable_keys_only_writes_and_redirects(): void
    {
        $post = Post::create(['user_id' => $this->user->id, 'title' => 'Old']);

        $this->actingAs($this->user)->put($this->url('update', $post->id), ['title' => 'New'])->assertRedirect();

        $this->assertSame('New', $post->refresh()->title);
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
