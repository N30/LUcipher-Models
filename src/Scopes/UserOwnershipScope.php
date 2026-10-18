<?php

namespace All1\LuModels\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Only the signed-in user's rows. Console commands are not scoped. A guest sees no rows at all:
 * 3.0.x built `where user_id = null` for a guest (and had a live dd() on one branch), which is the
 * wrong kind of empty — this one is explicit.
 */
class UserOwnershipScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (app()->runningInConsole() && ! app()->runningUnitTests()) {
            return;
        }

        $column = $model->getTable().'.'.(method_exists($model, 'ownerKeyName') ? $model->ownerKeyName() : 'user_id');

        if (! auth()->check()) {
            $builder->whereRaw('1 = 0');

            return;
        }

        $builder->where($column, auth()->id());
    }
}
