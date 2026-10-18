<?php

namespace All1\LuModels\Tests\Feature\Mcp;

use All1\LuModels\Audit\AuditEntry;
use All1\LuModels\Mcp\LuModelsServer;
use All1\LuModels\Mcp\Tools\CreateTool;
use All1\LuModels\Mcp\Tools\DeleteTool;
use All1\LuModels\Mcp\Tools\DescribeTool;
use All1\LuModels\Mcp\Tools\GetTool;
use All1\LuModels\Mcp\Tools\ListTool;
use All1\LuModels\Mcp\Tools\ModelsTool;
use All1\LuModels\Mcp\Tools\UpdateTool;
use All1\LuModels\Support\Descriptor;
use All1\LuModels\Tests\TestCase;
use App\Models\Post;
use App\Models\User;
use Illuminate\Testing\Fluent\AssertableJson;

/** Phase 5 §6: the fifth mouth. The same operations, policies and audit; dry run in the tense. */
class ToolsTest extends TestCase
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

    /** @return list<string> */
    private function toolNames(): array
    {
        $list = LuModelsServer::tools();

        return array_column((fn () => $this->items)->call($list), 'name');
    }

    public function test_the_server_offers_one_tool_per_exposed_model_and_operation(): void
    {
        $names = $this->toolNames();

        foreach (['lu_models_models', 'lu_models_describe', 'posts_list', 'posts_get', 'posts_create', 'posts_update', 'posts_delete'] as $name) {
            $this->assertContains($name, $names);
        }
        $this->assertSame([], array_filter($names, fn ($n) => str_starts_with($n, 'notes_')), 'Note is exposed on api only');
        $this->assertSame([], array_filter($names, fn ($n) => str_starts_with($n, 'memos_')), 'Memo is exposed nowhere');

        $tools = collect((fn () => $this->items)->call(LuModelsServer::tools()))->keyBy('name');
        $this->assertSame(['title', 'body', 'dry_run'], array_keys($tools['posts_create']['inputSchema']['properties']), 'the input schema is the operation\'s schema plus dry_run');
        $this->assertSame(['title'], $tools['posts_create']['inputSchema']['required']);
        $this->assertSame(['id'], $tools['posts_delete']['inputSchema']['required']);
        $this->assertTrue($tools['posts_delete']['annotations']['destructiveHint']);
        $this->assertTrue($tools['posts_list']['annotations']['readOnlyHint']);
        $this->assertArrayHasKey('filter', $tools['posts_list']['inputSchema']['properties']);
    }

    public function test_list_and_get_answer_in_the_api_envelope_without_hidden_fields(): void
    {
        $descriptor = Descriptor::for(Post::class);

        LuModelsServer::actingAs($this->user)->tool(new ListTool($descriptor), ['filter' => ['title' => ['like' => 'et']], 'sort' => 'title'])
            ->assertOk()
            ->assertStructuredContent(fn (AssertableJson $json) => $json
                ->where('meta.total', 1)
                ->where('data.0.title', 'Beta')
                ->missing('data.0.secret_token')
                ->missing('data.0.body')
                ->etc());

        $post = Post::where('title', 'Beta')->sole();
        LuModelsServer::actingAs($this->user)->tool(new GetTool($descriptor), ['id' => $post->id, 'include' => 'user'])
            ->assertOk()
            ->assertStructuredContent(fn (AssertableJson $json) => $json
                ->where('data.title', 'Beta')
                ->where('data.body', 'body of Beta')
                ->where('data.user.name', 'Neo')
                ->missing('data.secret_token')
                ->etc());
    }

    public function test_refusals_come_back_as_tool_errors(): void
    {
        $descriptor = Descriptor::for(Post::class);
        $post = Post::first();
        $other = User::create(['name' => 'Other', 'email' => 'other@example.test']);

        // the guest first: actingAs() keeps the user on the guard for the rest of the test
        LuModelsServer::tool(new ListTool($descriptor), [])->assertHasErrors(['403']);
        LuModelsServer::actingAs($this->user)->tool(new ListTool($descriptor), ['filter' => ['secret_token' => 'x']])->assertHasErrors(['400', 'not filterable']);
        LuModelsServer::actingAs($this->user)->tool(new GetTool($descriptor), ['id' => 999])->assertHasErrors(['404']);
        LuModelsServer::actingAs($other)->tool(new GetTool($descriptor), ['id' => $post->id])->assertHasErrors(['403']);
        LuModelsServer::actingAs($other)->tool(new DeleteTool($descriptor), ['id' => $post->id])->assertHasErrors(['403']);
        $this->assertSame(3, Post::count());
    }

    public function test_writes_go_through_the_writer_and_dry_run_speaks_in_the_conditional(): void
    {
        $descriptor = Descriptor::for(Post::class);
        $as = LuModelsServer::actingAs($this->user);

        $as->tool(new CreateTool($descriptor), ['title' => 'Delta', 'dry_run' => true])
            ->assertOk()
            ->assertStructuredContent(fn (AssertableJson $json) => $json->where('would', true)->where('message', 'Would create Post')->where('data.title', 'Delta')->etc());
        $this->assertSame(3, Post::count());
        $this->assertSame(0, AuditEntry::count());

        $as->tool(new CreateTool($descriptor), ['title' => 'Delta', 'secret_token' => 'spoof'])->assertHasErrors(['422', 'secret_token']);
        $as->tool(new CreateTool($descriptor), ['body' => 'no title'])->assertHasErrors(['422', 'title']);

        $as->tool(new CreateTool($descriptor), ['title' => 'Delta', 'body' => 'd'])
            ->assertOk()
            ->assertStructuredContent(fn (AssertableJson $json) => $json->missing('would')->where('data.title', 'Delta')->where('data.user_id', $this->user->id)->has('id')->etc());
        $delta = Post::where('title', 'Delta')->sole();
        $this->assertSame(1, AuditEntry::count());
        $this->assertSame('mcp', AuditEntry::first()->transport);

        $as->tool(new UpdateTool($descriptor), ['id' => $delta->id, 'title' => 'Delta 2', 'dry_run' => true])
            ->assertStructuredContent(fn (AssertableJson $json) => $json->where('would', true)->where('message', 'Would update Post #'.$delta->id)->etc());
        $this->assertSame('Delta', $delta->fresh()->title);
        $as->tool(new UpdateTool($descriptor), ['id' => $delta->id, 'title' => 'Delta 2'])->assertOk();
        $this->assertSame('Delta 2', $delta->fresh()->title);

        $as->tool(new DeleteTool($descriptor), ['id' => $delta->id, 'dry_run' => true])
            ->assertStructuredContent(fn (AssertableJson $json) => $json->where('would', true)->where('message', 'Would delete Post #'.$delta->id)->etc());
        $this->assertNotNull($delta->fresh());
        $as->tool(new DeleteTool($descriptor), ['id' => $delta->id])->assertOk();
        $this->assertNull($delta->fresh());
    }

    public function test_models_and_describe_tell_an_agent_what_it_may_do(): void
    {
        LuModelsServer::actingAs($this->user)->tool(ModelsTool::class)
            ->assertOk()
            ->assertStructuredContent(fn (AssertableJson $json) => $json
                ->where('count', 1)
                ->where('models.0.model', 'posts')
                ->where('models.0.tools', ['posts_list', 'posts_get', 'posts_create', 'posts_update', 'posts_delete'])
                ->etc());

        LuModelsServer::actingAs($this->user)->tool(DescribeTool::class, ['model' => 'posts'])
            ->assertOk()
            ->assertStructuredContent(fn (AssertableJson $json) => $json
                ->where('class', Post::class)
                ->where('key', 'id')
                ->where('relationships', ['user'])
                ->where('rules.create.title', 'required|string|max:120')
                ->has('tools.posts_create.properties.title')
                ->etc());

        LuModelsServer::actingAs($this->user)->tool(DescribeTool::class, ['model' => 'notes'])->assertHasErrors(['404']);
    }
}
