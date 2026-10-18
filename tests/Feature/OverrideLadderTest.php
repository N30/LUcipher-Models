<?php

namespace All1\LuModels\Tests\Feature;

use All1\LuModels\Tests\TestCase;
use App\Models\Post;
use App\Models\User;
use App\Policies\PostPolicy;
use Illuminate\Support\Facades\File;

/** The override ladder (study §2.9): the app wins by creating a file or class with the predictable name. */
class OverrideLadderTest extends TestCase
{
    public function test_the_package_view_is_used_when_the_app_has_no_view_folder(): void
    {
        $this->assertSame('lu_models::web.index', (new Post)->viewFile('index'));
    }

    public function test_an_app_view_folder_named_after_the_model_overrides_the_package_view(): void
    {
        $folder = resource_path('views/posts');
        File::ensureDirectoryExists($folder);
        File::put($folder.'/index.blade.php', 'app view');

        try {
            $this->assertSame('posts', (new Post)->viewFolder());
            $this->assertSame('posts.index', (new Post)->viewFile('index'));
            $this->assertSame('lu_models::web.show', (new Post)->viewFile('show'), 'only the views the app provides are overridden');
        } finally {
            File::deleteDirectory($folder);
        }
    }

    public function test_the_policy_receives_the_model_the_input_and_the_transport(): void
    {
        PostPolicy::$indexCalls = [];
        $user = User::create(['name' => 'Neo', 'email' => 'neo@example.test']);

        $this->actingAs($user)
            ->get(route('lu::web.resources', ['model' => 'posts', 'action' => 'index', 'search' => 'x']))
            ->assertOk();

        $this->assertCount(1, PostPolicy::$indexCalls);
        [$post, $data, $controllerType] = PostPolicy::$indexCalls[0];
        $this->assertInstanceOf(Post::class, $post);
        $this->assertSame('x', $data['search'] ?? null, 'the request input reaches the policy');
        $this->assertSame('web', $controllerType, 'the transport reaches the policy (study §2.3)');
    }
}
