<?php

namespace All1\LuModels\Tests\Feature;

use All1\LuModels\Actions\Create;
use All1\LuModels\Services\LuModels;
use All1\LuModels\Support\Keys;
use All1\LuModels\Support\Writer;
use All1\LuModels\Tests\TestCase;
use App\Extra\Event;
use App\Extra\EventPolicy;
use App\Extra\UnsafeEvent;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\HttpException;

/** What the writer injects, what the unsafe path writes, and how a slug names a record. */
class WriterTest extends TestCase
{
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::create(['name' => 'Neo', 'email' => 'neo@example.test']);
        Gate::policy(Event::class, EventPolicy::class);
        Gate::policy(UnsafeEvent::class, EventPolicy::class);
    }

    public function test_the_writer_injects_the_request_the_session_prefills_and_the_slug(): void
    {
        $this->actingAs($this->user);
        session(['event_seats' => 7, 'event_nonexistent' => 'ignored', 'other_title' => 'ignored']);

        $result = Writer::create(new Event, ['title' => 'Launch Day'], 'web');

        $this->assertFalse($result->failed());
        $event = Event::where('title', 'Launch Day')->sole();
        $this->assertSame('127.0.0.1', $event->ip_address);
        $this->assertSame('Symfony', $event->user_agent);
        $this->assertSame(7, $event->seats, 'the session prefill for a field the client left out');
        $this->assertSame('launch-day', $event->slug);
    }

    public function test_a_model_that_opts_out_of_the_create_safety_gets_every_key_written(): void
    {
        $this->actingAs($this->user);

        $service = (new Create)->perform(new LuModels, new UnsafeEvent, ['title' => 'Raw', 'seats' => 3], 'api');

        $this->assertSame(3, $service->model->fresh()->seats, 'seats is not fillable on UnsafeEvent and was written anyway');
    }

    public function test_a_slug_names_a_record_and_guests_need_the_models_opt_in(): void
    {
        $this->actingAs($this->user);
        Writer::create(new Event, ['title' => 'Launch Day'], 'web');
        auth()->logout();

        $this->assertSame('Launch Day', Keys::find(new Event, 'launch-day')->title, 'a signed-in caller or an internal lookup may use the slug');
        $this->assertNull(Keys::find(new Event, 'no-such-slug'));

        try {
            Keys::find(new Event, 'launch-day', guestOptIns: true);
            $this->fail('a guest needs the opt-in');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }

        $open = new Event;
        $open->allow_public_access_by_slug = true;
        $this->assertSame('Launch Day', Keys::find($open, 'launch-day', guestOptIns: true)->title);

        $any = new Event;
        $any->allow_any_unencrypted_access = true;
        $this->assertNotNull(Keys::find($any, (string) Event::first()->id, guestOptIns: true));
    }
}
