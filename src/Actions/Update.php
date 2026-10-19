<?php

namespace All1\LuModels\Actions;

use All1\LuModels\Events\Updated;
use All1\LuModels\Services\LuModels;
use All1\LuModels\Support\Descriptor;
use Illuminate\Database\Eloquent\Model;

/** Update a record through update() (respects $fillable) unless the model sets $update_safety = false. */
class Update extends Action
{
    public const VERB = 'Updation';

    public function name(): string
    {
        return 'update';
    }

    protected function inputSchema(Descriptor $descriptor): array
    {
        return $this->writeSchema($descriptor, 'edit', false);
    }

    /**
     * @param  Model  $model
     * @param  array<string, mixed>  $data
     */
    public function perform(LuModels $service, $model, array $data, ?string $controller_type): LuModels
    {
        if ($this->safetyOn($model, 'update_safety')) {
            $model->update($data);
        } else {
            // explicit opt-out: every submitted key is written
            foreach ($data as $key => $value) {
                $model->{$key} = $value;
            }
            $model->save();
        }

        $service->model = $model;

        $this->dispatchEvent($model, 'Updated', Updated::class, $data, $controller_type);

        return $service;
    }
}
