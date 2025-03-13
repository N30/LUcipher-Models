<?php

namespace All1\LuModels\Http\Controllers\Api;

use Illuminate\Http\Request;
use Str; 
 //use All1\LuModels\Services\LuModels;

class ApiResourceController
{
    public $model, $route_prefix, $aciton, $model_name, $meta, $action;

    public $columns;//all columns of such type model from route slug

    public $db_columns;//all columns that were pulled buy query builder

    public $filters=[];

    public $service;

    public $controller_type;

    /* API DOCUMENTATION **

    // GET /api/index/{model} list all records
    //exampel: /api/index/events?limit=1&sort=title&order=DESC&filter=title:Prof.%20Richard%20Ondricka%20DDS&paginate=1&page=4
    // GET /api/show/{model}/{id} show a single record
    //example: /api/show/events/1

    */

    public function __construct()
    {
        
        $this->controller_type = request()->route('type')??'web';
        //$input = explode('/', request()->path());
        $this->service = new \All1\LuModels\Services\LuModels();
        $controller_data = $this->service->controller_initialize($this->controller_type);
        $this->model = $this->service->model;
        $this->route_prefix = $controller_data['route_prefix'];
        $this->action = request()->route('action')??'index';//isset($input[2])? $input[2] : 'index';
        $this->model_name = $controller_data['model_name'];
        $this->meta = $controller_data['meta'];
        $this->columns = $controller_data['columns'];
    
        return false;
    }
    /**
     * Display a listing of the resource.
     */
    public function __invoke(Request $request, $model=null, $action=null, $id=null)
    {
        /*switch($this->aciton) {
            case 'list':
                return $this->index();
                break;
        }*/ 
       // dd("TEST");
       $method = $request->method();
       if($method=='GET') {
            $methodName = $action;
       }else {
            $methodName = strtolower($method).'_'.ucfirst($action);  
       }
        return ($this->{$this->action}($id));
    }


    public function create() {
        //nothing for API other controller types will override
    }
 

    public function index($a=null)
    { 
         $data = $this->service->controller_index();
         $this->filters = $this->service->filters;
            $this->db_columns = $this->service->db_columns;
            $this->model = $this->service->model;
            $this->columns = $this->service->controller_data['columns'];
            $this->route_prefix = $this->service->controller_data['route_prefix'];
            $this->action = $this->service->controller_data['action'];
            $this->meta = $this->service->controller_data['meta'];
            $this->controller_type = $this->service->type;
         return $data;
    }

     

    public function show($id)
    {
        return $this->model->find($id);
    }

    public function edit($id)
    {
        $this->model = ( $this->model->find($id) );

        $this->model->readFromData( request()->all() );

        return $this->model;
    }

    public function store()
    {

        //see if model has slug by checking if it has method getSlugOptions
        if( method_exists($this->model, 'getSlugOptions') ){
            $slug_col = $this->model->getSlugOptions()->generateSlugFrom[0];
            $slug = \Str::slug(request()->$slug_col);
            $data =  array_merge(  request()->except(['_token', '_method']) , ['slug'=> $slug ]);
        }else {
            $data = request()->except(['_token', '_method']);           
        }

        $data = $this->model->createFromData(  $data  );

        return ['message' => 'Record created', 'data' => $data ];
    }

    public function update($id)
    {
        $obj = $this->model->find($id);
        if(!$obj){
            return ['message' => 'Record not found', 'data' => [] ];
        }
        $data = $obj->updateFromData( request()->all() );

        return ['message' => 'Record updated', 'data' => $data ];
    }

    public function destroy($id)
    {
        //check if auth user owns the record
        //if not abort
        //if yes delete
        $obj = $this->model->find($id);
        if(!$obj){
            return ['message' => 'Record not found', 'data' => [] ];
        }
        //check if auth user owns the record
        //if not abort
        //if yes delete
        try {
            $data = $obj->deleteFromData( request()->all() );
            return ['message' => 'Record deleted', 'data' => $data ];
        } catch (\Throwable $e) {
            return ['message' => 'Record not deleted' ];
        }
    }
}
