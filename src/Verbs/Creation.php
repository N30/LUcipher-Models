<?php

namespace All1\LuModels\Verbs;

use Thunk\Verbs\Event;

class Creation extends Event
{
    public $model;
    public $data;
    public $controller_type;

    public function __construct($service, $model,$data,$controller_type)
    { 
        
        $this->model = $model;
        $this->data = $data;
        $this->controller_type = $controller_type;
        $this->service = $service;
    }

    public function handle()
    {
        $model = $this->model;
        $data = $this->data;
        $controller_type = $this->controller_type;

        if($model->creation_safety) {
            //create in a way that ensures getting the id
            $model = $model->create($data);
            //$model->fill($data);
            //$model->save(); 
        } else { 
            foreach($data as $k=>$v) {
                $model->$k = $v;
            }
            $model->save();
        }
 
        $this->service->model = $model;
        
        //See Events/README
        // Dispatch Laravel built-in event for side effects //example:app\Events\CRUD\UserCreated
        if(class_exists('\App\Events\\'.$model->action_folder.'\\'.ucfirst(class_basename($this))."Created" ) ) {
            $event = '\App\Events\\'.$model->action_folder.'\\'.ucfirst(class_basename($this))."Created";
              event( new $event($model, $data, $controller_type));
        }else { //run that action
              event(new \All1\LuModels\Events\Created($model, $data, $controller_type));
        }
       

        return $this->service;
        //after this verbs execute
        //event(strtolower(class_basename($this)).'.created', ['data' => $data]); //<-- THIS IS THE EVENT YOU WANT TO LISTEN FOR
         
    }
}
