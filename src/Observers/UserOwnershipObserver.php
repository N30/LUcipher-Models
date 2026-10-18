<?php

namespace All1\LuModels\Observers;

use Illuminate\Database\Eloquent\Model;

/**
 * Stamps the signed-in user on every record it creates. One observer watches every owned model for
 * its user_id, rather than one observer per model (the author's inversion, study §3).
 */
class UserOwnershipObserver
{
    public function creating(Model $model): void
    {
        if (auth()->check()) {
            $model->setAttribute($this->ownerKey($model), auth()->id());
        }
    }

    protected function ownerKey(Model $model): string
    {
        return method_exists($model, 'ownerKeyName') ? $model->ownerKeyName() : 'user_id';
    }
}
