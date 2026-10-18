<?php

namespace All1\LuModels\Concerns;

use All1\LuModels\Observers\UserOwnershipObserver;
use All1\LuModels\Scopes\UserOwnershipScope;

/**
 * A model that belongs to the signed-in user: creating stamps user_id with the current user, and
 * every query is scoped to their rows. Guests see nothing (the scope fails closed); console commands
 * and `Model::withoutGlobalScope(UserOwnershipScope::class)` see everything.
 *
 * The observer and the scope shipped in 3.0.x but nothing wired them; every app copied both files
 * and registered them per model (study §5.11). One `use OwnedByUser;` does that now.
 */
trait OwnedByUser
{
    public static function bootOwnedByUser(): void
    {
        static::observe(UserOwnershipObserver::class);
        static::addGlobalScope(new UserOwnershipScope);
    }

    public function ownerKeyName(): string
    {
        return 'user_id';
    }
}
