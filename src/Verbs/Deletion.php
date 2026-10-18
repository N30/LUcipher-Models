<?php

namespace All1\LuModels\Verbs;

class Deletion extends Verb
{
    public function handle()
    {
        $model = $this->model;

        $model->delete();

        //See Events/README
        // Dispatch Laravel built-in event for side effects //example: App\Events\CRUD\UserDeleted
        $this->dispatchEvent($model, 'Deleted', \All1\LuModels\Events\Deleted::class);

        $this->service->model = $model;

        return $this->service;
        //after this the service fires the string event {model}.deleted <-- THE EVENT YOU WANT TO LISTEN FOR
    }
}
