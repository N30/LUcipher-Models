<?php

namespace All1\LuModels\Livewire;

use Livewire\Component;
use Livewire\WithPagination;
use \All1\LuModels\Models\LuModel;

class Master extends Component
{
    use WithPagination;

    public $action;
    public   $model;
    public $model_full_namespace;
    public $id;
    public $service;
    public $route_prefix; 
    public $meta;
    public $columns;
    public $with;
    public $filters;
    public $db_columns; 
    public $controller_type;
    public $model_name;

    public function __construct()
    {
         
    }
    public function mount() {
        $this->action = request()->route('action') ;
        $this->model = request()->route('model') ;
        $this->id = request()->route('id') ;

        $service = new \All1\LuModels\Services\LuModels();
        $controller_data = $service->controller_initialize('spa');
        $this->route_prefix = $controller_data['route_prefix'];
        $this->model_name = $controller_data['model_name'];
        $this->meta = $controller_data['meta'];
        $this->columns = $controller_data['columns'];
        //dd($controller_data);
        //$this->model = $service->model;
        $model_full_namespace = get_class($service->model);
        //dd($model_full_namespace);
        $this->model_full_namespace = $model_full_namespace;
        $this->route_prefix = $controller_data['route_prefix']; 
       // $this->action = request()->route('action')??'index';//isset($input[2])? $input[2] : 'index';
        $this->model_name = $controller_data['model_name'];
        $this->meta = $controller_data['meta'];
        $this->columns = $controller_data['columns'];
        $this->controller_type = $service->type;
    }
 
    public function render()
    {
 
        $service = new \All1\LuModels\Services\LuModels();
        //dd($this->model_full_namespace);    
        $data =  $service->controller_index(new $this->model_full_namespace);
        //dd($this->model);
            $this->filters =  $service->filters;
           $this->db_columns = $service->db_columns;
           $this->model = $service->model->obj;
          // $this->columns = $service->controller_data['columns'];
          

         
        return view('lu_models::spa.master')
            
            ->with('data', $data)
            ->with('model', $this->model) 
            ->with('action', $this->action);
    }
}
