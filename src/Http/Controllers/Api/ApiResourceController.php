<?php

namespace All1\LuModels\Http\Controllers\Api;

use Illuminate\Http\Request;
use Str; 
use Validator;
 //use \All1\LuModels\Services\LuModels;

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

    public function __construct($force_model=null)
    {
        $model = request()->route('model') ?? $force_model;
        $this->controller_type =  explode('.',Str::after( request()->route()->getName() ,'lu::'))[0];
        //$input = explode('/', request()->path());
        $this->service = new \All1\LuModels\Services\LuModels();
        $controller_data = $this->service->controller_initialize($this->controller_type,$model);
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
    public function __invoke(Request $request,  $model=null, $action=null, $id=null)
    {
        /*switch($this->aciton) {
            case 'list':
                return $this->index();
                break;
        }*/ 
       // dd("TEST");
       //get this route name
        
       $method = $request->method();
        
       if($method=='GET') {
            $methodName = $action;
       }else {
            $methodName = strtolower($method).ucfirst($action);  //used to have underline between
       }
        return ($this->{$this->action}($id));
    }


    public function create() {
        //nothing for API other controller types will override
    }
 

    public function index($a=null)
    { 
            $this->service = \All1\LuModels\Services\LuModels::fromData('index',$this->service, $this->model, request()->all(), $this->controller_type);
            $data = $this->service->data;
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
        //if id is in encrypted format decrypt
        if( strlen($id) > 20 && !is_numeric($id) ) {
            $id = decrypt($id);
            $passed_id_type = 'encrypted';
        }elseif(is_numeric($id)) {
            $id = (int)$id;
            $passed_id_type = 'plain';
            if(!auth()->check() && $this->model->allow_public_access_by_id==false) {
                abort(403, 'You must be authenticated by API or login in order to view this record through this URL');
            }
        }else {
            $passed_id_type = 'slug';
            if(!auth()->check() && $this->model->allow_public_access_by_slug==false) {
                abort(403, 'You must be authenticated by API or login in order to view this record through this URL');  
            }
        }
        if($passed_id_type!== 'encrypted' && $this->model->allow_any_unencrypred_access==false) {
            abort(403, 'You must be authenticated by API or login in order to view this record through this URL');
        }

        if($passed_id_type=='slug') {
            $this->model = $this->model->where('slug', $id)->first();
        }else {
            $this->model = $this->model->find($id);
        }

        $this->service = \All1\LuModels\Services\LuModels::fromData('read',$this->service, $this->model, request()->all(), $this->controller_type);
        $this->model = $this->service->model;
            $data = $this->service->data;
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

    public function edit($id)
    {
        $this->model = ( $this->model->find($id) );
 
        if( method_exists($this->model, 'readFromData') ) { 

            $this->service  = $this->model->fromData('read', request()->except(['_token', '_method']));

        } else {
            $this->service = \All1\LuModels\Services\LuModels::fromData('read', $this->service, $this->model, request()->all(), $this->controller_type );
        }
        $this->model = $this->service->model;
        return $this->model;
    }

    public function store( $data = null)
    {
        
        if(!isset($data))  $data = request()->except(['_token', '_method']);   

        foreach($this->columns as $k=>$v) {
            if($v['Key']=='PRI' && $v['Extra']=='auto_increment') {
                unset($this->columns[$k]);
            }
            if($v['Field']=='ip_address') {
                $data =  array_merge( $data , ['ip_address'=> request()->ip() ]);
            }elseif($v['Field']=='user_id') {
                $data =  array_merge( $data , ['user_id'=> auth()->id() ]);
            }elseif($v['Field']=='user_agent') {
                $data =  array_merge( $data , ['user_agent'=> request()->header('User-Agent') ]);
            }
        }
        //opinionated type hinting LuModel rule:
        //foreach session starting with {model}_ that exists in db table but not in submissions add to attributesafterAll(function () {
        foreach( (session()->all()) as $k=>$v) {
            if( Str::startsWith($k, strtolower($this->model_name).'_') && !array_key_exists($k, $data) ) {
                //check it exists in db columns
                $exists = false;
                $pure_name = Str::after($k, strtolower($this->model_name).'_');
                foreach($this->columns as $col) {
                    if($col['Field']==$pure_name) {
                        $data =  array_merge( $data , [$pure_name=> $v ]);
                    }
                }
            }
        } 
        
        //see if model has slug by checking if it has method getSlugOptions
        if( method_exists($this->model, 'getSlugOptions') ){
            $slug_col = $this->model->getSlugOptions()->generateSlugFrom[0];
            $slug = \Str::slug(request()->$slug_col);
            $data =  array_merge( $data , ['slug'=> $slug ]);
        } 

        $validator = Validator::make($data, $this->model->store_validation_rules);
    
        if ($validator->fails()) {
            return ['errors'=>$validator ,'message'=>'There waas an issue with your input'];//)->withInput();
        }

        //dd($validated);
        //pass data and controller_type web, api, spa, etc.... (for record keeping)
        $model_copy_since_method_exist_is_mutating_it = $this->model;
        if(    method_exists($this->model, 'createFromData') ) {  
            $data = $this->model->fromData('create', $this->service,  $data  , $this->controller_type );
        } else {  
            $data = \All1\LuModels\Services\LuModels::fromData('create', $this->service, $model_copy_since_method_exist_is_mutating_it, $data, $this->controller_type );
        }
        //get last inserted column by
        //encrypt the id
        $id = encrypt($data->model->id);

        return ['message' => 'We have received and saved your information successfully.', 'data' => $data, 'id' => $id ];
    }

    public function update($id, $data=null)
    {
        if(!isset($data))  $data = request()->except(['_token', '_method']);

        $obj = $this->model->find($id);
        if(!$obj){
            return ['message' => 'Record not found', 'data' => [] ];
        } 

        if( method_exists($obj, 'updateFromData') ) { 
            $data = $obj->fromData('update', $data);
        } else {
            $data = \All1\LuModels\Services\LuModels::fromData('update', $this->service, $obj, $data, $this->controller_type );
        }

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
        
            if( method_exists($obj, 'deleteFromData') ) { 
                $data = $obj->fromData('delete', request()->except(['_token', '_method']));
            } else {
                $data = \All1\LuModels\Services\LuModels::fromData('delete', $this->service, $obj, request()->except(['_token', '_method']), $this->controller_type );
            }
            return ['message' => 'Record deleted', 'data' => $data ];
        
    }
}
