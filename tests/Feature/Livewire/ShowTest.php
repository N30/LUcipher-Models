<?php

namespace All1\LuModels\Tests\Feature\Livewire;

use All1\LuModels\Livewire\Show;
use All1\LuModels\Tests\TestCase;
use App\Models\Post;
use App\Models\User;
use Livewire\Livewire;

/** Phase 5 §5: one record on /spa, through the read operation. */
class ShowTest extends TestCase
{
    public function test_it_renders_what_show_may_read(): void
    {
        $user = User::create(['name' => 'Neo', 'email' => 'neo@example.test']);
        $post = Post::create(['user_id' => $user->id, 'title' => 'Alpha', 'body' => 'the body']);
        $post->forceFill(['secret_token' => 'TOKEN'])->save();

        Livewire::actingAs($user)->test(Show::class, ['model' => 'posts', 'id' => $post->id])
            ->assertSee('Alpha')->assertSee('the body')->assertDontSee('TOKEN')->assertDontSee('Secret Token')
            ->assertSee(route('lu::spa.resources', ['model' => 'posts', 'action' => 'edit', 'id' => $post->id]));
    }

    public function test_a_stranger_is_refused_and_a_missing_record_is_a_404(): void
    {
        $user = User::create(['name' => 'Neo', 'email' => 'neo@example.test']);
        $other = User::create(['name' => 'Other', 'email' => 'other@example.test']);
        $post = Post::create(['user_id' => $user->id, 'title' => 'Alpha']);

        Livewire::actingAs($other)->test(Show::class, ['model' => 'posts', 'id' => $post->id])->assertForbidden();
        Livewire::actingAs($user)->test(Show::class, ['model' => 'posts', 'id' => 999])->assertNotFound();
    }
}
