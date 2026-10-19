<?php

namespace All1\LuModels\Tests\Feature;

use All1\LuModels\Mcp\LuModelsServer;
use All1\LuModels\Models\LuModel;
use All1\LuModels\Support\Descriptor;
use All1\LuModels\Tests\TestCase;
use Workbench\App\Models\Comment;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;
use Workbench\App\Providers\WorkbenchServiceProvider;
use Workbench\Database\Seeders\DatabaseSeeder;

/** Phase 6 §3: the examples app under workbench/ runs, so the example cannot rot. */
class WorkbenchSmokeTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [WorkbenchServiceProvider::class, ...parent::getPackageProviders($app)];
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        // the fixture app's settings would win over the workbench provider's; the workbench's apply here
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('lu::models.models_path', dirname(__DIR__, 2).'/workbench/app/Models');
        $app['config']->set('lu::models.models', [Post::class => true, Comment::class => ['web', 'api', 'spa']]);
        $app['config']->set('lu::models.layout', 'components.layouts.app');
        $app['view']->addLocation(dirname(__DIR__, 2).'/workbench/resources/views');
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(dirname(__DIR__, 2).'/workbench/database/migrations');
    }

    protected function setUp(): void
    {
        parent::setUp(); // the migrations registered above have run by now
        $this->seed(DatabaseSeeder::class);
    }

    public function test_models_outside_app_models_are_discovered_with_plain_slugs(): void
    {
        $this->assertSame('Post', LuModel::relativeName(Post::class), 'relative to the models root, whatever the namespace');
        $this->assertSame('Comment', LuModel::getModels()[Comment::class]['relative']);
        $this->assertSame('posts', Descriptor::for(Post::class)->slug);
        $this->assertSame('comments', Descriptor::for(Comment::class)->slug);
    }

    public function test_the_examples_app_serves_every_transport_from_two_model_files_and_two_policies(): void
    {
        $demo = User::where('email', 'demo@example.test')->sole();
        $as = $this->actingAs($demo);

        $as->get('/web/posts')->assertOk()->assertSee('Hello, world')->assertSee('A draft')->assertDontSee("The neighbour's post", false)->assertDontSee('never shown');
        $as->get('/web/comments')->assertOk()->assertSee('Nice first post.');
        $as->get('/spa/posts')->assertOk()->assertSee('wire:', false)->assertSee('Hello, world');

        $as->getJson('/api/posts?filter[published]=1&sort=-id&fields=title')->assertOk()->assertJsonPath('meta.total', 2)->assertJsonPath('data.0.title', 'Notes on the grammar');
        $as->getJson('/api/openapi.json')->assertOk()->assertJsonPath('info.title', 'lucipher/models examples API')->assertJsonPath('paths./comments.get.operationId', 'listComments');
        $as->deleteJson('/api/posts/'.Post::first()->id)->assertForbidden(); // the policy says deleting is for people, not tokens

        $names = array_column((fn () => $this->items)->call(LuModelsServer::tools()), 'name');
        $this->assertContains('posts_delete', $names);
        $this->assertNotContains('comments_list', $names, 'Comment is kept off the agents');
    }
}
