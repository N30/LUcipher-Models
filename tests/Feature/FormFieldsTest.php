<?php

namespace All1\LuModels\Tests\Feature;

use All1\LuModels\Support\Form;
use All1\LuModels\Tests\TestCase;
use App\Extra\Event;
use App\Models\Post;
use App\Models\User;

/** Form::fields(): the operation's schema plus the descriptor's input kinds, one branch per column kind. */
class FormFieldsTest extends TestCase
{
    public function test_every_column_kind_gets_its_input_type_and_the_schema_decides_what_is_required(): void
    {
        $fields = collect(Form::fields(new Event, 'create'))->keyBy('name');

        $this->assertSame(['title', 'starts_on', 'starts_at', 'at', 'seats', 'free', 'contact_email', 'meta'], $fields->keys()->all(), 'ip_address, user_agent and slug are injected, never asked for');
        $this->assertSame('text', $fields['title']['type']);
        $this->assertTrue($fields['title']['required']);
        $this->assertSame(80, $fields['title']['maxlength']);
        $this->assertSame('date', $fields['starts_on']['type']);
        $this->assertSame('datetime-local', $fields['starts_at']['type']);
        $this->assertSame('time', $fields['at']['type']);
        $this->assertSame('number', $fields['seats']['type']);
        $this->assertSame('checkbox', $fields['free']['type']);
        $this->assertFalse($fields['free']['required'], 'a column with a default is not required');
        $this->assertSame('email', $fields['contact_email']['type']);
        $this->assertSame('textarea', $fields['meta']['input']);
    }

    public function test_an_existing_record_fills_the_values_in_the_shape_the_input_wants(): void
    {
        $event = Event::create(['title' => 'Launch', 'starts_on' => '2026-10-19', 'starts_at' => '2026-10-19 14:30:00', 'at' => '09:15:00', 'seats' => 12, 'free' => true]);

        $fields = collect(Form::fields($event, 'edit', ['seats' => 99]))->keyBy('name');

        $this->assertSame('2026-10-19', $fields['starts_on']['value']);
        $this->assertSame('2026-10-19T14:30', $fields['starts_at']['value']);
        $this->assertSame('09:15:00', $fields['at']['value']);
        $this->assertSame(99, $fields['seats']['value'], 'old input wins over the record');
        $this->assertTrue($fields['free']['value']);
        $this->assertFalse($fields['title']['required'], 'an update is partial');
    }

    public function test_a_super_admin_may_reassign_the_owner_on_edit(): void
    {
        $user = User::create(['name' => 'Neo', 'email' => 'neo@example.test']);
        $post = Post::create(['user_id' => $user->id, 'title' => 'Mine']);

        $this->actingAs($user);
        $this->assertSame(['title', 'body'], array_column(Form::fields($post, 'edit'), 'name'));

        $user->is_super = true;
        $this->assertSame(['user_id', 'title', 'body'], array_column(Form::fields($post, 'edit'), 'name'));
        $this->assertSame(['title', 'body'], array_column(Form::fields(new Post, 'create'), 'name'), 'never on create: the package stamps the owner');
    }
}
