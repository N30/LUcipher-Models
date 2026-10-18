<?php

namespace All1\LuModels\Tests\Feature\Api;

use All1\LuModels\Tests\TestCase;
use App\Models\Post;
use App\Models\User;

/** Phase 5 §1: filter, sort, fields and include on the listing; every name is checked against what the action may read. */
class QueryGrammarTest extends TestCase
{
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::create(['name' => 'Neo', 'email' => 'neo@example.test']);
        foreach (['Alpha' => 'body of Alpha', 'Beta' => 'body of Beta', 'Gamma' => null] as $title => $body) {
            $post = Post::create(['user_id' => $this->user->id, 'title' => $title, 'body' => $body]);
            $post->forceFill(['secret_token' => 'TOKEN-'.$title])->save();
        }
    }

    private function index(array $query = [])
    {
        return $this->actingAs($this->user)->getJson('/api/posts?'.http_build_query($query));
    }

    public function test_filters_by_equality_and_by_operator(): void
    {
        $this->index(['filter' => ['title' => 'Beta']])->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.title', 'Beta');
        $this->index(['filter' => ['title' => ['ne' => 'Beta']]])->assertJsonPath('meta.total', 2);
        $this->index(['filter' => ['title' => ['like' => 'et']]])->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.title', 'Beta');
        $this->index(['filter' => ['id' => ['gte' => 2]]])->assertJsonPath('meta.total', 2);
        $this->index(['filter' => ['id' => ['lt' => 2]]])->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.title', 'Alpha');
        $this->index(['filter' => ['id' => ['in' => '1,3']]])->assertJsonPath('meta.total', 2);
        $this->index(['filter' => ['id' => ['in' => [1, 2]]]])->assertJsonPath('meta.total', 2);
        $this->index(['filter' => ['id' => ['gt' => 1, 'lte' => 2]]])->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.title', 'Beta');
    }

    public function test_an_unknown_hidden_or_unreadable_filter_field_is_a_400_naming_it(): void
    {
        $this->index(['filter' => ['nope' => 'x']])->assertStatus(400)->assertJsonPath('message', 'The filter field nope is not filterable.');
        $this->index(['filter' => ['secret_token' => 'TOKEN-Beta']])->assertStatus(400)->assertJsonPath('message', 'The filter field secret_token is not filterable.');
        $this->index(['filter' => ['body' => ['null' => 1]]])->assertStatus(400, 'body is not readable on the index');
        $this->index(['filter' => ['title' => ['between' => '1,2']]])->assertStatus(400)->assertJsonPath('message', 'The filter operator between is not one of eq, ne, lt, lte, gt, gte, like, in, null.');
        $this->index(['filter' => 'title'])->assertStatus(400);
        $this->assertSame(3, Post::count());
    }

    public function test_sorts_by_one_or_more_readable_fields(): void
    {
        $this->index(['sort' => 'title'])->assertOk()->assertJsonPath('data.0.title', 'Alpha');
        $this->index(['sort' => '-title'])->assertJsonPath('data.0.title', 'Gamma');
        $this->index(['sort' => '-user_id,title'])->assertJsonPath('data.0.title', 'Alpha')->assertJsonPath('data.2.title', 'Gamma');
        $this->index(['sort' => ['title']])->assertJsonPath('data.0.title', 'Alpha');
        $this->index(['order' => 'asc'])->assertJsonPath('data.0.title', 'Alpha', 'the legacy order parameter still works');
    }

    public function test_an_unknown_or_hidden_sort_field_is_a_400(): void
    {
        $this->index(['sort' => 'nope'])->assertStatus(400)->assertJsonPath('message', 'The sort field nope is not sortable.');
        $this->index(['sort' => '-secret_token'])->assertStatus(400)->assertJsonPath('message', 'The sort field secret_token is not sortable.');
    }

    public function test_sparse_fields_keep_the_key_and_the_loaded_relationships(): void
    {
        $response = $this->index(['fields' => 'title', 'include' => 'user'])->assertOk();
        $row = $response->json('data.0');

        $this->assertSame('Gamma', $row['title']);
        $this->assertArrayHasKey('id', $row);
        $this->assertArrayHasKey('user', $row);
        $this->assertArrayNotHasKey('created_at', $row);
        $this->assertArrayNotHasKey('user_id', $row);
        $this->assertArrayNotHasKey('secret_token', $row);
    }

    public function test_a_field_the_action_may_not_read_cannot_be_selected(): void
    {
        $this->index(['fields' => 'secret_token'])->assertStatus(400)->assertJsonPath('message', 'The field secret_token cannot be selected.');
        $this->index(['fields' => 'title,body'])->assertStatus(400)->assertJsonPath('message', 'The field body cannot be selected.');
    }

    public function test_include_loads_descriptor_relationships_only(): void
    {
        $this->index(['include' => 'user'])->assertOk()->assertJsonPath('data.0.user.name', 'Neo');
        $this->index(['include' => ['user']])->assertOk()->assertJsonPath('data.0.user.name', 'Neo');
        $this->index(['include' => 'owner'])->assertStatus(400)->assertJsonPath('message', 'The include owner is not a relationship of Post.');
    }

    public function test_the_page_links_carry_the_grammar(): void
    {
        $response = $this->index(['per_page' => 1, 'sort' => 'title', 'filter' => ['id' => ['gte' => 1]], 'fields' => 'title'])->assertOk();

        $next = $response->json('links.next');
        $this->assertStringContainsString('page=2', $next);
        $this->assertStringContainsString('sort=title', $next);
        $this->assertStringContainsString('fields=title', $next);
        $this->assertStringContainsString(urlencode('filter[id][gte]').'=1', $next);
        $this->assertSame(3, $response->json('meta.last_page'));
    }
}
