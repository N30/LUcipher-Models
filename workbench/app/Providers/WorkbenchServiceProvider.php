<?php

namespace Workbench\App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Workbench\App\Models\Comment;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;
use Workbench\App\Policies\CommentPolicy;
use Workbench\App\Policies\PostPolicy;

/**
 * What an application would put in its own config/lu/models.php and AuthServiceProvider: the models it
 * exposes and on which transports, the transports it turns on, and its policies. The examples app has no
 * Sanctum, so the API and the MCP server are opted in unauthenticated here; policies still apply.
 */
class WorkbenchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        config()->set('auth.providers.users.model', User::class);

        config()->set('lu::models.models_path', dirname(__DIR__).'/Models');
        config()->set('lu::models.models', [
            Post::class => true,                    // every transport, agents included
            Comment::class => ['web', 'api', 'spa'], // never on mcp
        ]);
        foreach (['web', 'rest', 'api', 'spa', 'mcp'] as $transport) {
            config()->set("lu::models.enable_{$transport}_routes", true);
        }
        config()->set('lu::models.allow_unauthenticated_api', true); // no Sanctum in the examples app
        config()->set('lu::models.openapi.title', 'lucipher/models examples API');
        config()->set('lu::models.mcp.name', 'lucipher/models examples');
        config()->set('lu::models.mcp.local_user', 1); // `testbench mcp:start lu-models` runs as the demo user
    }

    public function boot(): void
    {
        Gate::policy(Post::class, PostPolicy::class);
        Gate::policy(Comment::class, CommentPolicy::class);
    }
}
