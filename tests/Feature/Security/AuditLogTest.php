<?php

namespace All1\LuModels\Tests\Feature\Security;

use All1\LuModels\Audit\AuditEntry;
use All1\LuModels\Audit\AuditLogger;
use All1\LuModels\Tests\TestCase;
use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

/** Phase 3 §6: every write through the package leaves one row behind. */
class AuditLogTest extends TestCase
{
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::create(['name' => 'Neo', 'email' => 'neo@example.test']);
    }

    private function url(string $action, $id = null): string
    {
        return route('lu::web.resources', array_filter(['model' => 'posts', 'action' => $action, 'id' => $id]));
    }

    public function test_a_create_through_the_web_transport_is_recorded(): void
    {
        $this->actingAs($this->user)->post($this->url('store'), ['title' => 'Fresh', 'body' => 'text'])->assertRedirect();

        $entry = AuditEntry::sole();
        $this->assertSame(Post::class, $entry->model_type);
        $this->assertSame((string) Post::sole()->id, $entry->model_id);
        $this->assertSame('created', $entry->action);
        $this->assertSame('web', $entry->transport);
        $this->assertSame($this->user->id, $entry->user_id);
        $this->assertSame('127.0.0.1', $entry->ip);
        $this->assertSame('Fresh', $entry->changes['after']['title']);
        $this->assertArrayNotHasKey('before', $entry->changes);
    }

    public function test_an_update_records_before_and_after_for_the_changed_keys_only(): void
    {
        $post = Post::create(['user_id' => $this->user->id, 'title' => 'Old', 'body' => 'same']);

        $this->actingAs($this->user)->putJson(route('lu::api.resources', ['model' => 'posts', 'action' => 'update', 'id' => $post->id]), ['title' => 'New'])->assertOk();

        $entry = AuditEntry::where('action', 'updated')->sole();
        $this->assertSame('api', $entry->transport);
        $this->assertSame(['title' => 'Old'], $entry->changes['before']);
        $this->assertSame('New', $entry->changes['after']['title']);
        $this->assertArrayNotHasKey('body', $entry->changes['after'], 'unchanged keys are not recorded');
    }

    public function test_a_delete_records_what_was_removed(): void
    {
        $post = Post::create(['user_id' => $this->user->id, 'title' => 'Bye']);

        $this->actingAs($this->user)->delete($this->url('destroy', $post->id))->assertRedirect();

        $entry = AuditEntry::where('action', 'deleted')->sole();
        $this->assertSame((string) $post->id, $entry->model_id);
        $this->assertSame('Bye', $entry->changes['before']['title']);
        $this->assertArrayNotHasKey('after', $entry->changes);
    }

    public function test_hidden_attributes_are_never_stored(): void
    {
        $post = Post::create(['user_id' => $this->user->id, 'title' => 'T']);
        $post->forceFill(['secret_token' => 'TOP-SECRET'])->save();

        $entry = AuditLogger::record('deleted', $post, [], 'api');

        $this->assertStringNotContainsString('TOP-SECRET', json_encode($entry->changes));
        $this->assertArrayNotHasKey('secret_token', $entry->changes['before']);
        $this->assertSame('T', $entry->changes['before']['title']);
    }

    public function test_store_values_off_keeps_only_the_attribute_names(): void
    {
        config(['lu::models.audit.store_values' => false]);

        $this->actingAs($this->user)->post($this->url('store'), ['title' => 'Fresh'])->assertRedirect();

        $changes = AuditEntry::sole()->changes;
        $this->assertArrayHasKey('fields', $changes);
        $this->assertContains('title', $changes['fields']);
        $this->assertStringNotContainsString('Fresh', json_encode($changes));
    }

    public function test_the_log_can_be_turned_off(): void
    {
        config(['lu::models.audit.enabled' => false]);

        $this->actingAs($this->user)->post($this->url('store'), ['title' => 'Fresh'])->assertRedirect();

        $this->assertDatabaseCount('posts', 1);
        $this->assertDatabaseCount('lu_models_audit', 0);
    }

    public function test_a_missing_table_never_blocks_the_write(): void
    {
        Schema::drop('lu_models_audit');

        $this->actingAs($this->user)->post($this->url('store'), ['title' => 'Fresh'])->assertRedirect();

        $this->assertDatabaseCount('posts', 1);
    }
}
