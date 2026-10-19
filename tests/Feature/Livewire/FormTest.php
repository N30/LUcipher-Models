<?php

namespace All1\LuModels\Tests\Feature\Livewire;

use All1\LuModels\Audit\AuditEntry;
use All1\LuModels\Livewire\Form;
use All1\LuModels\Tests\TestCase;
use App\Models\Post;
use App\Models\User;
use Livewire\Livewire;

/** Phase 5 §5: the form on /spa writes through the same pipeline as the API. */
class FormTest extends TestCase
{
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::create(['name' => 'Neo', 'email' => 'neo@example.test']);
    }

    public function test_create_renders_the_schema_fields_validates_and_saves(): void
    {
        $component = Livewire::actingAs($this->user)->test(Form::class, ['model' => 'posts'])
            ->assertSet('key', null)
            ->assertSee('Title')->assertSee('Body')->assertDontSee('Secret Token')
            ->assertSee('Shown as the headline')
            ->set('form.title', '')->call('save')->assertHasErrors(['title'])->assertDispatched('lu-models:toast');

        $this->assertSame(0, Post::count());

        $component->set('form.title', 'Fresh')->set('form.body', 'the body')->call('save')->assertHasNoErrors();

        $post = Post::where('title', 'Fresh')->sole();
        $this->assertSame($this->user->id, $post->user_id, 'user_id is injected, never taken from the form');
        $this->assertSame('the body', $post->body);
        $component->assertRedirect(route('lu::spa.resources', ['model' => 'posts', 'action' => 'show', 'id' => $post->id]));
        $this->assertSame(1, AuditEntry::count());
    }

    public function test_a_guarded_key_is_refused_whole(): void
    {
        Livewire::actingAs($this->user)->test(Form::class, ['model' => 'posts'])
            ->set('form.title', 'Spoof')->set('form.secret_token', 'injected')
            ->call('save')->assertHasErrors(['secret_token']);

        $this->assertSame(0, Post::count());
    }

    public function test_edit_prefills_and_updates(): void
    {
        $post = Post::create(['user_id' => $this->user->id, 'title' => 'Old', 'body' => 'old body']);

        Livewire::actingAs($this->user)->test(Form::class, ['model' => 'posts', 'id' => $post->id])
            ->assertSet('key', $post->id)
            ->assertSet('form.title', 'Old')->assertSet('form.body', 'old body')
            ->set('form.title', 'Updated')->set('form.body', '')
            ->call('save')->assertHasNoErrors()
            ->assertRedirect(route('lu::spa.resources', ['model' => 'posts', 'action' => 'show', 'id' => $post->id]));

        $post->refresh();
        $this->assertSame('Updated', $post->title);
        $this->assertNull($post->body, 'an emptied field is written as null');
    }

    public function test_a_stranger_cannot_edit(): void
    {
        $post = Post::create(['user_id' => $this->user->id, 'title' => 'Mine']);
        $other = User::create(['name' => 'Other', 'email' => 'other@example.test']);

        // refused on mount, through the read operation, before the record ever reaches the form
        Livewire::actingAs($other)->test(Form::class, ['model' => 'posts', 'id' => $post->id])->assertForbidden();
        $this->assertSame('Mine', $post->fresh()->title);
    }
}
