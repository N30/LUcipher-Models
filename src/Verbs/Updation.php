<?php

namespace All1\LuModels\Verbs;

class Updation extends Verb
{
    public function handle()
    {
        $model = $this->model;
        $data = $this->data;

        //v3.0.1: 3.0.0 tested an undeclared `updation_safety`, so every update took the branch
        //below and wrote every submitted key past $fillable. The flag is `update_safety` and an
        //undeclared flag now counts as on.
        if ($this->safetyOn($model, 'update_safety')) {
            //update() fills through $fillable / $guarded
            $model->update($data);
        } else {
            //explicit opt-out ($update_safety = false): every submitted key is written
            foreach ($data as $k => $v) {
                $model->$k = $v;
            }
            $model->save();
        }

        $this->service->model = $model;

        //See Events/README
        // Dispatch Laravel built-in event for side effects //example: App\Events\CRUD\UserUpdated
        $this->dispatchEvent($model, 'Updated', \All1\LuModels\Events\Updated::class);

        return $this->service;
        //after this the service fires the string event {model}.updated <-- THE EVENT YOU WANT TO LISTEN FOR
    }
}
