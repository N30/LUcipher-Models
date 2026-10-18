<?php

namespace All1\LuModels\Tests\Feature\Livewire;

use All1\LuModels\Livewire\Index;
use All1\LuModels\Tests\TestCase;
use App\Models\Post;
use App\Models\User;
use Livewire\Livewire;

/** Phase 5 §5: the listing on /spa is the web index blade, live, over the same query the API runs. */
class IndexTest extends TestCase
{
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::create(['name' => 'Neo', 'email' => 'neo@example.test']);
        foreach (['Alpha', 'Beta', 'Gamma'] as $title) {
            Post::create(['user_id' => $this->user->id, 'title' => $title, 'body' => 'body of '.$title]);
        }
    }

    public function test_it_lists_searches_sorts_and_paginates(): void
    {
        Livewire::actingAs($this->user)->test(Index::class, ['model' => 'posts'])
            ->assertSet('modelClass', Post::class)
            ->assertSee('Alpha')->assertSee('Gamma')
            ->assertDontSee('body of Alpha', 'body is not readable on the index')
            ->set('search', 'Beta')->assertSee('Beta')->assertDontSee('Alpha')
            ->set('search', '')->call('sortBy', 'title')->assertSet('sort', 'title')->assertSeeHtmlInOrder(['Alpha', 'Beta', 'Gamma'])
            ->call('sortBy', 'title')->assertSet('sort', '-title')->assertSeeHtmlInOrder(['Gamma', 'Beta', 'Alpha'])
            ->set('perPage', 2)->assertSee('Gamma')->assertDontSee('Alpha')
            ->call('resetFilters')->assertSet('sort', null)->assertSet('search', '');
    }

    public function test_delete_removes_the_row_and_toasts(): void
    {
        $post = Post::where('title', 'Beta')->sole();

        Livewire::actingAs($this->user)->test(Index::class, ['model' => 'posts'])
            ->call('delete', $post->id)
            ->assertDispatched('lu-models:toast')
            ->assertDontSee('Beta');

        $this->assertDatabaseMissing('posts', ['id' => $post->id]);
    }

    public function test_a_stranger_cannot_delete(): void
    {
        $post = Post::where('title', 'Beta')->sole();
        $other = User::create(['name' => 'Other', 'email' => 'other@example.test']);

        Livewire::actingAs($other)->test(Index::class, ['model' => 'posts'])->call('delete', $post->id)->assertForbidden();
        $this->assertDatabaseHas('posts', ['id' => $post->id]);
    }

    public function test_an_unexposed_or_unknown_model_is_a_404(): void
    {
        Livewire::actingAs($this->user)->test(Index::class, ['model' => 'memos'])->assertNotFound();
        Livewire::actingAs($this->user)->test(Index::class, ['model' => 'nothings'])->assertNotFound();
    }

    public function test_the_spa_page_mounts_the_component_for_each_action(): void
    {
        $post = Post::where('title', 'Alpha')->sole();
        $as = $this->actingAs($this->user);

        $as->get('/spa/posts')->assertOk()->assertSee('livewire.min.js', false)->assertSee('wire:model', false)->assertSee('Alpha')->assertSee('id="lu-models-toasts"', false);
        $as->get('/spa/posts/show/'.$post->id)->assertOk()->assertSee('body of Alpha');
        $as->get('/spa/posts/create')->assertOk()->assertSee('name="title"', false);
        $as->get('/spa/posts/edit/'.$post->id)->assertOk()->assertSee('value="Alpha"', false);
        $as->get('/spa/posts/destroy/'.$post->id)->assertNotFound();
        $as->get('/spa/posts/show')->assertNotFound();
        $as->get('/spa/memos')->assertNotFound();
    }

    public function test_a_guest_is_refused_by_the_policy_on_the_page(): void
    {
        $this->get('/spa/posts')->assertForbidden();
    }
}
