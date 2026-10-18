<?php

namespace All1\LuModels\Verbs;

use All1\LuModels\Events\Deleted;

class Deletion extends Verb
{
    public function handle()
    {
        $model = $this->model;

        $model->delete();

        // See Events/README
        // Dispatch Laravel built-in event for side effects //example: App\Events\CRUD\UserDeleted
        $this->dispatchEvent($model, 'Deleted', Deleted::class);

        $this->service->model = $model;

        return $this->service;
        // after this the service fires the string event {model}.deleted <-- THE EVENT YOU WANT TO LISTEN FOR
    }
}
