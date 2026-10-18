<?php

namespace All1\LuModels\Verbs;

class Creation extends Verb
{
    public function handle()
    {
        $model = $this->model;
        $data = $this->data;

        if ($this->safetyOn($model, 'creation_safety')) {
            //create() respects $fillable and returns the persisted model (with its id)
            $model = $model->create($data);
        } else {
            //explicit opt-out ($creation_safety = false): every submitted key is written
            foreach ($data as $k => $v) {
                $model->$k = $v;
            }
            $model->save();
        }

        $this->model = $model;
        $this->service->model = $model;

        //See Events/README
        // Dispatch Laravel built-in event for side effects //example: App\Events\CRUD\UserCreated
        $this->dispatchEvent($model, 'Created', \All1\LuModels\Events\Created::class);

        return $this->service;
        //after this the service fires the string event {model}.created <-- THE EVENT YOU WANT TO LISTEN FOR
    }
}
