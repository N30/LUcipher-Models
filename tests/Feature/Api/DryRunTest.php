<?php

namespace All1\LuModels\Tests\Feature\Api;

use All1\LuModels\Audit\AuditEntry;
use All1\LuModels\Events\Created;
use All1\LuModels\Tests\TestCase;
use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Facades\Event;

/** Phase 5 §1: a write with dry_run is authorized and validated, answers in the conditional, and changes nothing. */
class DryRunTest extends TestCase
{
    protected User $user;

    protected Post $post;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::create(['name' => 'Neo', 'email' => 'neo@example.test']);
        $this->post = Post::create(['user_id' => $this->user->id, 'title' => 'Alpha']);
    }

    public function test_a_dry_run_create_says_what_it_would_do_and_writes_nothing(): void
    {
        Event::fake([Created::class]);

        $this->actingAs($this->user)->postJson('/api/posts?dry_run=1', ['title' => 'Delta'])
            ->assertOk()
            ->assertJsonPath('would', true)
            ->assertJsonPath('message', 'Would create Post')
            ->assertJsonPath('data.title', 'Delta')
            ->assertJsonMissingPath('id');

        $this->assertSame(1, Post::count());
        $this->assertSame(0, AuditEntry::count());
        Event::assertNotDispatched(Created::class);
    }

    public function test_a_dry_run_still_validates(): void
    {
        $this->actingAs($this->user)->postJson('/api/posts?dry_run=1', ['body' => 'no title'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['title']);
        $this->actingAs($this->user)->postJson('/api/posts?dry_run=1', ['title' => 'x', 'secret_token' => 'spoof'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['secret_token']);
    }

    public function test_a_dry_run_update_and_delete_name_the_record_and_leave_it(): void
    {
        $as = $this->actingAs($this->user);

        $as->patchJson('/api/posts/'.$this->post->id.'?dry_run=1', ['title' => 'Alpha 2'])
            ->assertOk()->assertJsonPath('would', true)->assertJsonPath('message', 'Would update Post #'.$this->post->id);
        $as->deleteJson('/api/posts/'.$this->post->id.'?dry_run=true')
            ->assertOk()->assertJsonPath('would', true)->assertJsonPath('message', 'Would delete Post #'.$this->post->id);
        $as->deleteJson('/api/posts/999?dry_run=1')->assertNotFound();

        $this->assertSame('Alpha', $this->post->fresh()->title);
        $this->assertSame(0, AuditEntry::count());
    }

    public function test_a_dry_run_is_still_authorized(): void
    {
        $other = User::create(['name' => 'Other', 'email' => 'other@example.test']);

        $this->actingAs($other)->patchJson('/api/posts/'.$this->post->id.'?dry_run=1', ['title' => 'x'])->assertForbidden();
        $this->actingAs($other)->deleteJson('/api/posts/'.$this->post->id.'?dry_run=1')->assertForbidden();
    }

    public function test_the_web_transport_ignores_dry_run(): void
    {
        $this->actingAs($this->user)
            ->post(route('lu::web.resources.store', ['model' => 'posts', 'action' => 'store', 'dry_run' => 1]), ['title' => 'Real'])
            ->assertRedirect();

        $this->assertSame(2, Post::count());
    }
}
