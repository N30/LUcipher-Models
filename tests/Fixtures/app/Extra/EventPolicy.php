<?php

namespace App\Extra;

use App\Models\User;

class EventPolicy
{
    public function index(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function show(User $user, Event $event): bool
    {
        return true;
    }

    public function update(User $user, Event $event): bool
    {
        return true;
    }

    public function delete(User $user, Event $event): bool
    {
        return true;
    }
}
