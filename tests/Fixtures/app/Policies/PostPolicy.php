<?php

namespace App\Policies;

use App\Models\Post;
use App\Models\User;

class PostPolicy
{
    /** @var list<array{0: mixed, 1: mixed, 2: mixed}> what index() was called with, beyond the user */
    public static array $indexCalls = [];

    /**
     * @param  mixed  $post
     * @param  mixed  $data
     * @param  mixed  $controllerType
     */
    public function index(User $user, $post = null, $data = null, $controllerType = null): bool
    {
        static::$indexCalls[] = [$post, $data, $controllerType];

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

    public function delete(User $user, Post $post): bool
    {
        return $post->user_id === $user->id;
    }
}
