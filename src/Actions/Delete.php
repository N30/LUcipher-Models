<?php

namespace All1\LuModels\Actions;

use All1\LuModels\Events\Deleted;
use All1\LuModels\Services\LuModels;

/** Delete a record. */
class Delete extends Action
{
    public const VERB = 'Deletion';

    public function name(): string
    {
        return 'delete';
    }

    public function perform(LuModels $service, $model, array $data, ?string $controller_type): LuModels
    {
        $model->delete();

        $service->model = $model;

        $this->dispatchEvent($model, 'Deleted', Deleted::class, $data, $controller_type);

        return $service;
    }
}
