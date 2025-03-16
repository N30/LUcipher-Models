<?php

namespace All1\LuModels\Verbs;

use Thunk\Verbs\Event;

class Updation extends Event
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

        if($model->updation_safety) {
            $model->update($data);
            //$model->fill($data);
            //$model->save();
        }else { 
            foreach($data as $k=>$v) {
                $model->$k = $v;
            }
            $model->save();
        }
        
        //See Events/README
        // Dispatch Laravel built-in event for side effects
        if(class_exists('\App\Events\\'.$model->action_folder.'\\'.ucfirst(class_basename($this))."Updated" ) ) {
            $event = '\App\Events\\'.$model->action_folder.'\\'.ucfirst(class_basename($this))."Updated";
              event( new $event($model, $data, $controller_type));
        }else { //run that action
              event(new \All1\LuModels\Events\Updated($model, $data, $controller_type));
        }

        return $model;
        //after this verbs execute
        //event(strtolower(class_basename($this)).'.Updated', ['data' => $data]); //<-- THIS IS THE EVENT YOU WANT TO LISTEN FOR

    }
}
