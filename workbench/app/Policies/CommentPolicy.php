<?php

namespace Workbench\App\Policies;

use Workbench\App\Models\Comment;
use Workbench\App\Models\User;

class CommentPolicy
{
    public function index(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function show(User $user, Comment $comment): bool
    {
        return true;
    }

    public function update(User $user, Comment $comment): bool
    {
        return $comment->user_id === $user->id;
    }

    public function delete(User $user, Comment $comment): bool
    {
        return $comment->user_id === $user->id;
    }
}
