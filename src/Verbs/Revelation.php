<?php

namespace All1\LuModels\Verbs;

class Revelation extends Verb
{
    public function handle()
    {
        $model = $this->model;

        //`read_safety` (3.0.0 tested a misspelled, undeclared `revelation_safety`) is reserved:
        //a read writes nothing, so there is no unsafe branch to guard here.

        $this->model = $this->service->controller_show($this->model);

        //See Events/README
        // Dispatch Laravel built-in event for side effects //example: App\Events\CRUD\UserRead
        $this->dispatchEvent($model, 'Read', \All1\LuModels\Events\Read::class);

        return $this->service;
    }
}
