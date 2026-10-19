<?php

namespace All1\LuModels\Tests\Feature;

use All1\LuModels\Tests\TestCase;
use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;

/** The /rest transport: the web pages for developers and admins, writes answered with a flash and back. */
class RestTransportTest extends TestCase
{
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::create(['name' => 'Neo', 'email' => 'neo@example.test']);
    }

    private function url(string $action, $id = null): string
    {
        return route('lu::rest.resources'.(in_array($action, ['store', 'update', 'destroy'], true) ? '.'.$action : ''), array_filter(['model' => 'posts', 'action' => $action, 'id' => $id]));
    }

    public function test_the_pages_render_from_the_shared_blades(): void
    {
        Post::create(['user_id' => $this->user->id, 'title' => 'Hello']);

        Route::get('/login', fn () => 'login')->name('login'); // the auth middleware redirects a guest here
        $this->get($this->url('index'))->assertRedirect('/login'); // the rest transport is hardcoded behind auth (guest first: actingAs persists)
        $this->actingAs($this->user)->get($this->url('index'))->assertOk()->assertSee('Hello');
        $this->actingAs($this->user)->get($this->url('create'))->assertOk()->assertSee('name="title"', false);
    }

    public function test_a_write_answers_with_a_flash_and_back(): void
    {
        $this->actingAs($this->user)->from('/somewhere')->post($this->url('store'), ['title' => 'Fresh'])
            ->assertRedirect('/somewhere')
            ->assertSessionHas('flash.banner', 'We have received and saved your information successfully.');
        $this->assertSame(1, Post::where('title', 'Fresh')->count());

        $this->actingAs($this->user)->from('/somewhere')->post($this->url('store'), ['body' => 'no title'])
            ->assertRedirect('/somewhere')
            ->assertSessionHasErrors(['title']);

        $post = Post::first();
        $this->actingAs($this->user)->from('/somewhere')->delete($this->url('destroy', $post->id))->assertRedirect('/somewhere');
        $this->assertNull($post->fresh());
    }

    public function test_an_app_view_named_after_the_model_wins_on_rest_and_web(): void
    {
        View::addLocation(dirname(__DIR__).'/Fixtures/resources/views/custom');
        Post::create(['user_id' => $this->user->id, 'title' => 'Hello']);

        $this->actingAs($this->user)->get($this->url('index'))->assertOk()->assertSee('CUSTOM POSTS INDEX (1 rows)');
        $this->actingAs($this->user)->get(route('lu::web.resources', ['model' => 'posts', 'action' => 'index']))->assertOk()->assertSee('CUSTOM POSTS INDEX (1 rows)');
    }
}
