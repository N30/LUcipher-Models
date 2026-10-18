<?php

namespace All1\LuModels\Tests\Feature\Security;

use All1\LuModels\Tests\TestCase;
use App\Models\Post;
use App\Models\User;

/** Phase 3 §7: one user cannot reach another user's record through any action or transport. */
class IdorTest extends TestCase
{
    protected User $owner;

    protected User $other;

    protected Post $post;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::create(['name' => 'Owner', 'email' => 'owner@example.test']);
        $this->other = User::create(['name' => 'Other', 'email' => 'other@example.test']);
        $this->post = Post::create(['user_id' => $this->owner->id, 'title' => 'Mine', 'body' => 'private']);
    }

    private function url(string $transport, string $action, $id = null): string
    {
        return route("lu::{$transport}.resources", array_filter(['model' => 'posts', 'action' => $action, 'id' => $id]));
    }

    public function test_the_owner_can_show_edit_update_and_delete(): void
    {
        $this->actingAs($this->owner)->get($this->url('web', 'show', encrypt($this->post->id)))->assertOk()->assertSee('Mine');
        $this->actingAs($this->owner)->get($this->url('web', 'edit', $this->post->id))->assertOk();
        $this->actingAs($this->owner)->put($this->url('web', 'update', $this->post->id), ['title' => 'Still mine'])->assertRedirect();
        $this->assertSame('Still mine', $this->post->refresh()->title);
        $this->actingAs($this->owner)->delete($this->url('web', 'destroy', $this->post->id))->assertRedirect();
        $this->assertDatabaseMissing('posts', ['id' => $this->post->id]);
    }

    public function test_another_user_is_denied_on_every_action_and_transport(): void
    {
        $this->actingAs($this->other);

        $this->get($this->url('web', 'show', encrypt($this->post->id)))->assertForbidden();
        $this->get($this->url('web', 'edit', $this->post->id))->assertForbidden();
        $this->put($this->url('web', 'update', $this->post->id), ['title' => 'Taken'])->assertForbidden();
        $this->delete($this->url('web', 'destroy', $this->post->id))->assertForbidden();

        $this->getJson($this->url('api', 'show', encrypt($this->post->id)))->assertForbidden();
        $this->putJson($this->url('api', 'update', $this->post->id), ['title' => 'Taken'])->assertForbidden();
        $this->deleteJson($this->url('api', 'destroy', $this->post->id))->assertForbidden();

        $this->put($this->url('rest', 'update', $this->post->id), ['title' => 'Taken'])->assertForbidden();

        $this->assertSame('Mine', $this->post->refresh()->title);
        $this->assertDatabaseHas('posts', ['id' => $this->post->id]);
    }

    public function test_a_guest_cannot_enumerate_records_by_plain_id(): void
    {
        $this->get($this->url('web', 'show', $this->post->id))->assertForbidden();
        $this->getJson($this->url('api', 'show', $this->post->id))->assertForbidden();
    }

    public function test_a_store_belongs_to_the_caller_whatever_user_id_the_payload_claims(): void
    {
        $this->actingAs($this->other)
            ->postJson($this->url('api', 'store'), ['title' => 'Planted', 'user_id' => $this->owner->id])
            ->assertOk();

        $this->assertSame($this->other->id, Post::where('title', 'Planted')->sole()->user_id);
    }

    public function test_a_missing_record_is_404_not_a_crash(): void
    {
        $this->actingAs($this->owner)->get($this->url('web', 'show', encrypt(999)))->assertNotFound();
        $this->actingAs($this->owner)->get($this->url('web', 'edit', 999))->assertNotFound();
        $this->actingAs($this->owner)->getJson($this->url('api', 'show', encrypt(999)))->assertNotFound();
    }
}
