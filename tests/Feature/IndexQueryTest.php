<?php

namespace All1\LuModels\Tests\Feature;

use All1\LuModels\Tests\TestCase;
use App\Models\Post;
use App\Models\User;

/** Phase 4 §7: one query behind index and show; the request can only search and order what it may read. */
class IndexQueryTest extends TestCase
{
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::create(['name' => 'Neo', 'email' => 'neo@example.test']);
        foreach (['Alpha', 'Beta', 'Gamma'] as $title) {
            $post = Post::create(['user_id' => $this->user->id, 'title' => $title, 'body' => 'body of '.$title]);
            $post->forceFill(['secret_token' => 'TOKEN-'.$title])->save();
        }
    }

    private function index(array $query = [])
    {
        return $this->actingAs($this->user)->getJson(route('lu::api.resources', ['model' => 'posts', 'action' => 'index'] + $query));
    }

    public function test_it_paginates_and_honours_per_page_and_order(): void
    {
        // LuModel declares index_default_order = DESC; 3.0.x ignored it
        $this->index()->assertOk()->assertJsonPath('total', 3)->assertJsonPath('per_page', 15)->assertJsonPath('data.0.title', 'Gamma');
        $this->index(['order' => 'asc'])->assertJsonPath('data.0.title', 'Alpha');
        $this->index(['per_page' => 2])->assertJsonPath('per_page', 2)->assertJsonCount(2, 'data');
        $this->index(['per_page' => 9999])->assertJsonPath('per_page', 15);
        $this->index(['order' => 'junk; drop table posts'])->assertOk()->assertJsonPath('data.0.title', 'Gamma');
    }

    public function test_limit_returns_a_flat_list(): void
    {
        $this->index(['limit' => 2])->assertOk()->assertJsonCount(2)->assertJsonMissingPath('total');
    }

    public function test_search_covers_readable_fields_only(): void
    {
        $this->index(['search' => 'Beta'])->assertJsonPath('total', 1)->assertJsonPath('data.0.title', 'Beta');
        $this->index(['search' => 'TOKEN-Beta', 'search_fields' => ['secret_token']])->assertJsonPath('total', 0);
        $this->index(['search' => 'body of Beta', 'search_fields' => ['body']])->assertJsonPath('total', 0);
        $this->index(['search' => 'Gamma', 'search_fields' => ['title', 'secret_token']])->assertJsonPath('total', 1);
    }

    public function test_with_must_be_an_array_and_relations_can_be_named(): void
    {
        $this->index(['with' => 'user'])->assertStatus(400);
        $this->index(['with' => ['user']])->assertOk()->assertJsonPath('data.0.user.name', 'Neo');
    }

    public function test_show_uses_the_same_path_and_the_detail_page_sees_body(): void
    {
        $post = Post::where('title', 'Beta')->sole();

        $this->actingAs($this->user)
            ->getJson(route('lu::api.resources', ['model' => 'posts', 'action' => 'show', 'id' => encrypt($post->id)]))
            ->assertOk()
            ->assertJsonPath('0.title', 'Beta')
            ->assertJsonPath('0.body', 'body of Beta')
            ->assertJsonMissingPath('0.secret_token');
    }
}
