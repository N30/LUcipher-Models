<?php

namespace All1\LuModels\Livewire;

use Livewire\Component;
use Livewire\WithPagination;
use \All1\LuModels\Models\LuModel;
use All1\LuModels\Http\Controllers\Api\ApiResourceController;
class Master extends Component
{
    use WithPagination;

    public $action;
    public   $model;
    public $model_full_namespace;
    public $id;
    //$service; DONT TURN ON - DONT WANT IT TO TURN INTO HYDRATED VARIABLE NOT SUPPORTED public $service;
    public $route_prefix; 
    public $meta;
    public $columns;
    public $with;
    public $filters;
    public $db_columns; 
    public $controller_type;
    public $model_name;
    public $model_attributes = []; ///to store and bind with wire:model
   

    public function __construct()
    {
         
    }

    public function mount() {
        $this->action = request()->route('action') ;
        $this->model = request()->route('model') ;
        $this->id = request()->route('id') ;
        if($this->action == '' || $this->action == null) {
            $this->action = 'index';
        }
        $service = new \All1\LuModels\Services\LuModels();
        $controller_data = $service->controller_initialize('spa');
        $this->route_prefix = $controller_data['route_prefix'];
        $this->model_name = $controller_data['model_name'];
        $this->meta = $controller_data['meta'];
        $this->columns = $controller_data['columns'];
        foreach($this->columns as $k=>$v) {
            $this->model_attributes[$v['Field']] = '';
        }
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
        $this->service = \All1\LuModels\Services\LuModels::fromData('index',$service, new $this->model_full_namespace, request()->all(), $this->controller_type);
            $data = $this->service->data;
            $this->filters = $this->service->filters;
            $this->db_columns = $this->service->db_columns;
            $this->model = $this->service->model;
           foreach(array_values($this->db_columns) as $v) {
                if(!isset($this->model_attributes[$v])) $this->model_attributes[$v] = '';
           }
        return view('lu_models::spa.master')     
            ->with('data', $data)
            ->with('model', $this->model) 
            ->with('route_prefix', $this->route_prefix)
            ->with('meta', $this->meta)
            ->with('columns', $this->columns)
            ->with('filters', $this->filters)
            ->with('db_columns', $this->db_columns)
            ->with('controller_type', $this->controller_type)
            ->with('model_name', $this->model_name)
            ->with('action', $this->action);
    }


    public function beforeStore() {
        $filteredData = array_filter($this->model_attributes, function($value) {
            return !empty($value);
        });
        
        $this->store($filteredData);
    }

    public function store($filteredData) {
         
        //dd($filteredData);
        $api_controller = new ApiResourceController($this->model_name);
        $result = $api_controller->store($filteredData);
        $this->afterStore($result);
    }

    public function afterStore($result) {
        $this->js(" 
        window.toast('Data Created', { description: '".$result['message']."', type: 'success' })");
    }


    public function beforeUpdate() {
         
        $filteredData = array_filter($this->model_attributes, function($value) {
            return !empty($value);
        });
        
        $this->update($filteredData);
    }

    public function update($filteredData) {
         
        //dd($filteredData);
        $api_controller = new ApiResourceController($this->model_name);
        $result = $api_controller->update($this->id, $filteredData);
        $this->afterUpdate($result);
    }

    public function afterUpdate($result) {
        $this->js(" 
        window.toast('Data Created', { description: '".$result['message']."', type: 'success' })");
    }
}
