<?php

namespace All1\LuModels\Tests\Feature\Security;

use All1\LuModels\Support\FieldVisibility;
use All1\LuModels\Tests\TestCase;
use App\Models\Post;
use App\Models\User;

/** Phase 3 §3–4: $hidden never reads out; $lu_fields narrows per action. */
class VisibilityTest extends TestCase
{
    protected User $user;

    protected Post $post;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::create(['name' => 'Neo', 'email' => 'neo@example.test']);
        $this->post = Post::create(['user_id' => $this->user->id, 'title' => 'Visible title', 'body' => 'Body text']);
        $this->post->forceFill(['secret_token' => 'TOP-SECRET'])->save();
    }

    public function test_hidden_attributes_never_appear_in_api_or_web_output(): void
    {
        $api = $this->actingAs($this->user)->getJson(route('lu::api.resources', ['model' => 'posts', 'action' => 'index']))->assertOk();
        $this->assertStringNotContainsString('TOP-SECRET', $api->getContent());
        $this->assertStringNotContainsString('secret_token', $api->getContent());

        $web = $this->actingAs($this->user)->get(route('lu::web.resources', ['model' => 'posts', 'action' => 'index']))->assertOk();
        $this->assertStringNotContainsString('TOP-SECRET', $web->getContent());
        $web->assertSee('Visible title');
    }

    public function test_lu_fields_keeps_a_field_out_of_the_index_but_on_the_detail_page(): void
    {
        $index = $this->actingAs($this->user)->getJson(route('lu::api.resources', ['model' => 'posts', 'action' => 'index']))->assertOk();
        $this->assertStringNotContainsString('Body text', $index->getContent());

        $this->actingAs($this->user)
            ->get(route('lu::web.resources', ['model' => 'posts', 'action' => 'index']))
            ->assertOk()
            ->assertDontSee('Body text');
    }

    public function test_the_resolver_rules(): void
    {
        $visibility = FieldVisibility::for(new Post);

        $this->assertFalse($visibility->visible('secret_token', 'index'), '$hidden is never read');
        $this->assertFalse($visibility->visible('secret_token', 'show'));
        $this->assertFalse($visibility->writable('secret_token', 'create'), 'not fillable');
        $this->assertFalse($visibility->visible('body', 'index'), 'kept from index by $lu_fields');
        $this->assertTrue($visibility->visible('body', 'show'));
        $this->assertTrue($visibility->writable('body', 'store'), 'store is create');
        $this->assertTrue($visibility->writable('body', 'update'), 'update is edit');
        $this->assertTrue($visibility->visible('title', 'index'), 'unnamed fields keep the default');
        $this->assertFalse($visibility->writable('id', 'create'), 'id is not fillable');
        $this->assertSame(['secret_token', 'id'], $visibility->rejectedWrites(['title', 'secret_token', 'id'], 'update'));
        $this->assertSame(['id', 'title', 'user.name'], $visibility->readable(['id', 'title', 'body', 'secret_token', 'user.name'], 'index'));
    }
}
