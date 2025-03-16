<?php

namespace All1\LuModels\Verbs;

use Thunk\Verbs\Event;

class Deletion extends Event
{
    public $model;
    public $data;
    public $controller_type;

    public function __construct($service, $model,$data,$controller_type)
    {

        $this->model = $model;
        $this->data = $data;
        $this->controller_type = $controller_type;
    }

    public function handle()
    {
        $model = $this->model;
        $data = $this->data;
        $controller_type = $this->controller_type;

        $model->delete();

        //See Events/README
        // Dispatch Laravel built-in event for side effects
        if(class_exists('\App\Events\\'.$model->action_folder.'\\'.ucfirst(class_basename($this))."Deleted" ) ) {
            $event = '\App\Events\\'.$model->action_folder.'\\'.ucfirst(class_basename($this))."Deleted";
              event( new $event($model, $data, $controller_type));
        }else { //run that action
              event(new \All1\LuModels\Events\Deleted($model, $data, $controller_type));
        }

        $this->service->model=$model;
        return $this->service;
        //after this verbs execute
        //event(strtolower(class_basename($this)).'.deleted', ['data' => $data]); //<-- THIS IS THE EVENT YOU WANT TO LISTEN FOR

    }
}
