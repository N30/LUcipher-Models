<?php

namespace All1\LuModels\Actions;

use All1\LuModels\Events\Created;
use All1\LuModels\Services\LuModels;
use All1\LuModels\Support\Descriptor;
use Illuminate\Database\Eloquent\Model;

/** Create a record through create() (respects $fillable) unless the model sets $creation_safety = false. */
class Create extends Action
{
    public const VERB = 'Creation';

    public function name(): string
    {
        return 'create';
    }

    protected function inputSchema(Descriptor $descriptor): array
    {
        return $this->writeSchema($descriptor, 'create', true);
    }

    /**
     * @param  Model  $model
     * @param  array<string, mixed>  $data
     */
    public function perform(LuModels $service, $model, array $data, ?string $controller_type): LuModels
    {
        if ($this->safetyOn($model, 'creation_safety')) {
            $model = $model->create($data);
        } else {
            // explicit opt-out: every submitted key is written
            foreach ($data as $key => $value) {
                $model->{$key} = $value;
            }
            $model->save();
        }

        $service->model = $model;

        // example override: App\Events\CRUD\UserCreated
        $this->dispatchEvent($model, 'Created', Created::class, $data, $controller_type);

        return $service;
    }
}
