<?php

namespace All1\LuModels\Verbs;

use Thunk\Verbs\Event;

class Revelation extends Event
{
    public $model;
    public $data;
    public $controller_type;
    public $service;

    public function __construct($service, $model,$data,$controller_type)
    {
        $this->service = $service;
        $this->model = $model;
        $this->data = $data;
        $this->controller_type = $controller_type;
    }

    public function handle()
    {
        $model = $this->model;
        $data = $this->data;
        $controller_type = $this->controller_type;

        if($model->revelation_safety) {
            //$model->read($data);
            //$model->fill($data);
            //$model->save();
        }
     
        $this->model = $this->service->controller_show( $this->model);

         
        //See Events/README
        // Dispatch Laravel built-in event for side effects
        if(class_exists('\App\Events\\'.$model->action_folder.'\\'.ucfirst(class_basename($this))."Read" ) ) {
            $event = '\App\Events\\'.$model->action_folder.'\\'.ucfirst(class_basename($this))."Read";
              event( new $event($model, $data, $controller_type));
        }else { //run that action
              event(new \All1\LuModels\Events\Read($model, $data, $controller_type));
        }
 
        return $this->service;
        //after this verbs execute
        //event(strtolower(class_basename($this)).'.Updated', ['data' => $data]); //<-- THIS IS THE EVENT YOU WANT TO LISTEN FOR

    }
}
