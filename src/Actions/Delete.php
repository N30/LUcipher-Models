<?php

namespace All1\LuModels\Actions;

use All1\LuModels\Events\Deleted;
use All1\LuModels\Services\LuModels;
use All1\LuModels\Support\Descriptor;
use Illuminate\Database\Eloquent\Model;

/** Delete a record. */
class Delete extends Action
{
    public const VERB = 'Deletion';

    public function name(): string
    {
        return 'delete';
    }

    protected function inputSchema(Descriptor $descriptor): array
    {
        return $this->keySchema($descriptor);
    }

    /**
     * @param  Model  $model
     * @param  array<string, mixed>  $data
     */
    public function perform(LuModels $service, $model, array $data, ?string $controller_type): LuModels
    {
        $model->delete();

        $service->model = $model;

        $this->dispatchEvent($model, 'Deleted', Deleted::class, $data, $controller_type);

        return $service;
    }
}
