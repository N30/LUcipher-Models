<?php

namespace All1\LuModels\Tests\Feature;

use All1\LuModels\Events\Created;
use All1\LuModels\Events\Deleted;
use All1\LuModels\Events\ModelEvent;
use All1\LuModels\Events\Updated;
use All1\LuModels\Tests\TestCase;
use App\Models\Post;
use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

/** Phase 4 §5: typed, broadcastable write events. */
class EventsTest extends TestCase
{
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::create(['name' => 'Neo', 'email' => 'neo@example.test']);
    }

    public function test_the_three_write_events_share_the_contract_and_are_off_by_default(): void
    {
        $post = Post::create(['user_id' => $this->user->id, 'title' => 'T']);

        foreach ([Created::class => 'created', Updated::class => 'updated', Deleted::class => 'deleted'] as $class => $action) {
            $event = new $class($post, ['title' => 'T'], 'api');
            $this->assertInstanceOf(ModelEvent::class, $event);
            $this->assertInstanceOf(ShouldBroadcast::class, $event);
            $this->assertSame($action, $event->action());
            $this->assertSame('lu-models.'.$action, $event->broadcastAs());
            $this->assertFalse($event->broadcastWhen(), 'broadcasting is opt-in');
        }
    }

    public function test_the_channel_is_the_owners_private_channel_or_the_records(): void
    {
        $post = Post::create(['user_id' => $this->user->id, 'title' => 'T']);
        $channels = (new Created($post, [], 'web'))->broadcastOn();
        $this->assertCount(1, $channels);
        $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
        $this->assertSame('private-App.Models.User.'.$this->user->id, (string) $channels[0]);

        $this->assertSame('private-lu-models.users.'.$this->user->id, (string) (new Updated($this->user, [], 'web'))->broadcastOn()[0], 'no user_id → the record\'s own channel');

        config(['lu::models.broadcast.channel' => 'tenant.{table}.{key}']);
        $this->assertSame('private-tenant.posts.'.$post->id, (string) (new Deleted($post, [], 'web'))->broadcastOn()[0]);
    }

    public function test_the_payload_carries_the_record_without_hidden_attributes(): void
    {
        $post = Post::create(['user_id' => $this->user->id, 'title' => 'T', 'body' => 'b']);
        $post->forceFill(['secret_token' => 'TOP-SECRET'])->save();
        $post->update(['title' => 'U']);

        $payload = (new Updated($post, ['title' => 'U'], 'api'))->broadcastWith();

        $this->assertSame('updated', $payload['action']);
        $this->assertSame(Post::class, $payload['model']);
        $this->assertSame($post->id, $payload['key']);
        $this->assertSame('U', $payload['attributes']['title']);
        $this->assertArrayNotHasKey('secret_token', $payload['attributes']);
        $this->assertSame(['title' => 'U'], array_intersect_key($payload['changes'], ['title' => 1]));
        $this->assertArrayNotHasKey('secret_token', $payload['changes']);
        $this->assertSame('api', $payload['transport']);
        $this->assertStringNotContainsString('TOP-SECRET', json_encode($payload));

        $this->assertSame(['id' => $post->id], (new Deleted($post, [], 'web'))->broadcastWith()['attributes'], 'a delete carries only the key');
    }

    public function test_a_write_through_a_transport_dispatches_the_typed_event_with_the_transport(): void
    {
        Event::fake([Created::class]);

        $this->actingAs($this->user)->postJson(route('lu::api.resources', ['model' => 'posts', 'action' => 'store']), ['title' => 'Fresh'])->assertOk();

        Event::assertDispatched(Created::class, fn (Created $e) => $e->model->title === 'Fresh' && $e->controller_type === 'api' && $e->data['title'] === 'Fresh');
    }

    public function test_when_enabled_the_event_is_broadcast(): void
    {
        config(['lu::models.broadcast.enabled' => true, 'broadcasting.default' => 'log']);
        Log::spy();

        $this->actingAs($this->user)->postJson(route('lu::api.resources', ['model' => 'posts', 'action' => 'store']), ['title' => 'Live'])->assertOk();

        Log::shouldHaveReceived('info')->withArgs(fn ($message) => str_contains((string) $message, 'lu-models.created') && str_contains((string) $message, 'private-App.Models.User.'.$this->user->id))->atLeast()->once();
    }
}
