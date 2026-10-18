<?php

namespace App\Policies;

use App\Models\User;

class NotePolicy
{
    public function index(User $user): bool
    {
        return true;
    }
}
