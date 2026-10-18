<?php

namespace All1\LuModels\Verbs;

use All1\LuModels\Events\Index;

class Annexation extends Verb
{
    public function handle()
    {
        $model = $this->model;

        $this->service->controller_index($this->model);

        // See Events/README
        // Dispatch Laravel built-in event for side effects //example: App\Events\CRUD\UserIndex
        $this->dispatchEvent($model, 'Index', Index::class);

        return $this->service;
    }
}
