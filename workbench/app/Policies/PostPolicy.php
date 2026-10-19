<?php

namespace Workbench\App\Policies;

use Workbench\App\Models\Post;
use Workbench\App\Models\User;

/** Every signed-in user may list and create; a post is read, edited and deleted by its owner. The transport arrives as the last argument. */
class PostPolicy
{
    public function index(User $user, ?Post $post = null, mixed $data = null, ?string $transport = null): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function show(User $user, Post $post): bool
    {
        return $post->user_id === $user->id;
    }

    public function update(User $user, Post $post): bool
    {
        return $post->user_id === $user->id;
    }

    public function delete(User $user, Post $post, mixed $data = null, ?string $transport = null): bool
    {
        return $post->user_id === $user->id && $transport !== 'api'; // deleting is for people, not tokens
    }
}
