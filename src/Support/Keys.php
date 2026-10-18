<?php

namespace All1\LuModels\Support;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;

/**
 * How a request names a record: by encrypted key (always), by plain key or by slug. A signed-in caller
 * may use any of them, the policy decides what they reach; a guest may use a plain key or a slug only
 * where the model opts in ($allow_public_access_by_id, $allow_public_access_by_slug, or
 * $allow_any_unencrypted_access for both). 3.0.x refused plain keys for everyone unless the model opted
 * in, which kept the shipped UI's own links from reaching the pages they pointed at.
 */
final class Keys
{
    /** The record, or null when none matches. 403 for a guest naming a record the model does not let guests name. */
    public static function find(Model $model, mixed $id, bool $guestOptIns = false): ?Model
    {
        [$kind, $key] = self::resolve($model, $id);

        if ($guestOptIns && $kind !== 'encrypted' && ! auth()->check() && ! self::guestMayUse($model, $kind)) {
            abort(403, 'Sign in, or use the encrypted id, to reach this record through this URL.');
        }

        return $kind === 'slug'
            ? $model->newQuery()->where('slug', $key)->first()
            : $model->newQuery()->find($key);
    }

    /**
     * What kind of key the request sent, and the key itself: a number is the plain key, a value that
     * decrypts is an encrypted key, anything else is a slug when the model has one.
     *
     * @return array{0: string, 1: mixed}
     */
    public static function resolve(Model $model, mixed $id): array
    {
        if ($id === null || is_numeric($id)) {
            return ['plain', $id];
        }

        try {
            return ['encrypted', decrypt($id)];
        } catch (DecryptException) {
            $has_slug = Descriptor::for($model)->field('slug') !== null;

            return [$has_slug ? 'slug' : 'plain', $id];
        }
    }

    /** Whether a guest may name a record by this kind of key: the model's own public opt-ins. */
    public static function guestMayUse(Model $model, string $kind): bool
    {
        if (($model->allow_any_unencrypted_access ?? false) || ($model->allow_any_unencrypred_access ?? false)) {
            return true;
        }

        return (bool) ($kind === 'slug' ? ($model->allow_public_access_by_slug ?? false) : ($model->allow_public_access_by_id ?? false));
    }
}
