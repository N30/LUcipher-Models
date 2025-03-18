<?php

namespace All1\LuModels\Services;

use \All1\LuModels\Models\LuModel;

use Illuminate\Http\Request;
use Str; 

class LuModels
{

    public $controller_data = []; 
    public $model;
    public $type;
    public $data;
    public $db_columns;
    public $filters=[];
    public $controller_type; 
 
    //force_model is for livewire initiliing when there is no request()->reoute('model')
    public function controller_initialize($type='api', $force_model=NULL) {

        $this->type = $type;
        $this->controller_data['action'] = request()->route('action')??'index';//isset($input[2])? $input[2] : 'index';
        $model_variable = $force_model ?? request()->route('model'); 

        if(!in_array($this->controller_data['action'], ['exception_actions_that_dont_need_model', 'another_one'])){

            //get the model name from the first folder in the url
            
            $tmp = str_replace('~', '\\-', $model_variable);
            //turn $model to from reout group name to model name
            $relative_namespace = Str::studly(Str::singular($tmp));  
           
            $this->controller_data['model_name'] =  class_basename($relative_namespace);
            
            $this->controller_data['route_prefix'] = $model_variable;
           
            $model_full_namespace = 'App\Models\\' .  ucfirst($relative_namespace) ;
 
            //check if the model exists
            
            if(!class_exists($model_full_namespace)){
                //sometimes things that end in 'es' removes the es but the e is from the word, like hoe hence check
                if(class_exists('App\Models\\' .  ucfirst($this->controller_data['model_name']).'e')){
                    $model_full_namespace = 'App\Models\\' .  ucfirst($this->controller_data['model_name']).'e';
                }else {
                    abort(404, Str::headline( $relative_namespace ) . ' is not a thing, no route found');
                }
            }

            $this->model = new $model_full_namespace; 
 
            $this->controller_data['meta'] = LuModel::getMetaData($model_full_namespace);
 
            //$this->controller_data['columns'] = \Schema::getColumnListing($this->controller_data['model->getTable()) ;
            $this->controller_data['columns'] = \DB::select('SHOW FULL COLUMNS FROM '.$this->model->getTable());
            
            $sample=NULL;
            if(in_array($this->controller_data['action'], ['create','edit','need_sample']))  $sample = $this->model->first();

            foreach($this->controller_data['columns'] as $k=> $c){
                $this->controller_data['columns'][$k] = (array) $c;
                $clean_type = preg_replace('/\s*\([^)]*\)/', '', $c->Type);
                $max_length = preg_replace('/[^0-9]/', '', $c->Type);
                $input_type = match($clean_type){
                    'int' => 'number',
                    'varchar' => 'text',
                    'text' => 'textarea',
                    'tinytext' => 'textarea',
                    'longtext' => 'textarea',
                    'date' => 'date',
                    'datetime' => 'date',
                    'timestamp' => 'date',
                    'time' => 'date', 
                    'float' => 'number',
                    default => 'text', 
                };
                $this->controller_data['columns'][$k]['description'] = $c->Comment==NULL?'' : $c->Comment.' ,'; 
                $this->controller_data['columns'][$k]['description'] = $this->controller_data['columns'][$k]['description'] .( 'Default: '. ($c->Default==NULL?'NULL':$c->Default) );
                $this->controller_data['columns'][$k]['required'] = $c->Null=='NO'?true:false;
                $this->controller_data['columns'][$k]['clean_type'] = $clean_type;
                $this->controller_data['columns'][$k]['input_type'] = $input_type;
                $this->controller_data['columns'][$k]['max_length'] = $max_length ?? 524288;
                $this->controller_data['columns'][$k]['sample'] = $sample==NULL?'':$sample->{$c->Field};
            }
            
        }

        return $this->controller_data;
    }


    public function controller_index($model) {
        $this->model = $model;
        $perpage = request()->input('per_page', 15);
        $filters = request()->input('filters', NULL);
        $with = request()->input('with', NULL);
        $select = request()->input('select', NULL);
        $sort = request()->input('sort', NULL);
        $order = request()->input('order', NULL);
        $limit = request()->input('limit', NULL); 
        $page = request()->input('page', NULL);
        $search = request()->input('search', NULL);
        $search_fields = request()->input('search_fields', $this->model->default_search_columns??NULL);

        //determine method paginate/get
        if(request()->has('limit')){
            $pagination_method = false;
        }else {
            $pagination_method = true;
        }

        if($this->model->default_loaded_relationships){
            $default_relationships = $this->model->default_loaded_relationships;
        }else {
            //see if method exists
            if(method_exists($this->model, 'getModelRelationships')){
                $default_relationships = $this->model->getModelRelationships();
            }else {
                $default_relationships = [];
                //get all columns ending in id
                if(!isset($this->controller_data['columns']) && count($this->controller_data['columns'])  ){    
                    foreach($this->controller_data['columns'] as $k=>$v){
                        if(Str::endsWith($v['Field'], '_id')){
                            $default_relationships[] = Str::beforeLast($v['Field'], '_id');
                        }
                    }
                }
                ///LATER HERE ALSO CHECK ALL TABLE AND LOOK FOR $mode_id on them and look for pivtor tables with name of $model
                //and accordin create belongs to and belongs to many relationships.
            }
            //also include the relationship of those models
            foreach($default_relationships as $r){
                $model_name = 'App\Models\\' .  Str::singular(ucfirst($r)) ;
                try { 
                    $child_model = new $model_name; 
                    if(method_exists($child_model, 'getModelRelationships')){
                        foreach($child_model->getModelRelationships() as $c){
                            $default_relationships[] = $r.'.'.$c;
                        }
                    }
                } catch (\Throwable $e) {
                    // Ignore methods that throw errors (non-relationship methods)
                }
            }
        } 

         
         
        if( !is_iterable($with) & $with!==NULL){ 
            abort(500, 'with parameter must be an array');
        }
        //samfe for field
        //$fields = array_merge($this->model->index_relationships_columns, request()->input('fields', []));
        if(!isset($_GET['fields'])) {
            $_GET['fields'] = [];
        }
       
        //dd($this->model->index_relationships_columns);
        // dd(request()->fields);
        
        $q = $this->model->select( [ "*" ]);
        
        if(isset($this->model->default_loaded_relationships)){ 
            foreach($this->model->default_loaded_relationships as $i){ 
                $q->with([
                    $i => function ($query) use($i){ 
                        // Only select specific columns for the relationship (if needed)
                        $query->select( isset($this->model->index_relationships_columns[$i])?$this->model->index_relationships_columns[$i]:'*');  // Adjust to the columns you want for the related model
                    }
                ]);
            }
        }
         
        
        if($with!==NULL){
            foreach($with as $i){
                if($i!==NULL && isset( $this->model->index_relationships_columns[$i] )){
                    $q->with([
                        $i => function ($query) use($i){
                            // Only select specific columns for the relationship (if needed)
                            $query->select( $this->model->index_relationships_columns[$i]);  // Adjust to the columns you want for the related model
                        }
                    ]);
                }
            }
        }

        //where search term
        if($search!==NULL && $search_fields!==NULL){
            $q->where(function($query) use($search, $search_fields){
                foreach($search_fields as $f){
                    $query->orWhere($f, 'LIKE', '%'.$search.'%');
                }
            });
        }
         
        $q->orderBy($this->model->index_default_sort??'id', $order??'ASC');
         //   dd($this->model->get());
        //return the records either all or paginated
        if($pagination_method){
            $data = $q->paginate($perpage);
            $data->appends([
                'per_page' =>$perpage,
                'filters' =>$filters,
                'with' =>$with, 
                'sort' =>$sort,
                'order' =>$order,
            ]);
        }else{
            //return as data
            $data =  $q->get();
        }

        
        $db_columns= array_keys($data[0]->getAttributes());
         
        foreach($data as $d) {
            if(isset($this->model->hidden_columns)){
                foreach($this->model->hidden_columns as $c){
                unset($d->{$c});
                }
            }
        } 
        
        foreach($data[0]->getRelations() as $k=>$rel) {
            if(is_iterable($rel) &&  count($rel)>0){  
                foreach($rel[0]->getAttributes() as $c=>$v) {
                    $db_columns[$k.'.'.$c] = $k.'.'.$c;
                }
            } elseif($rel!==NULL &&  method_exists($rel, 'getAttributes') ) { 
                foreach($rel->getAttributes() as $c=>$v) {
                    $db_columns[$k.'.'.$c] = $k.'.'.$c;
                }
            }
        }
        //dd($data[0]->getRelations()); 
        $this->db_columns = $db_columns;
         
        $this->filters=[
            'with' =>$with,
            'sort' =>$sort,
            'order' =>$order,
            'limit' =>$limit,
            'page' =>$page,
            'search' =>$search,
            'search_fields' =>$search_fields,
        ];

        $this->data = $data;
        return $data;
    }



    public function controller_show($model) {
        $this->model = $model;
         
        $filters = request()->input('filters', NULL);
        $with = request()->input('with', NULL);
        $select = request()->input('select', NULL);
        $sort = request()->input('sort', NULL);
        $order = request()->input('order', NULL);
        $limit = request()->input('limit', NULL); 
        $page = request()->input('page', NULL);
        $search = request()->input('search', NULL);
        $search_fields = request()->input('search_fields', $this->model->default_search_columns??NULL);
 

        if($this->model->default_loaded_relationships){
            $default_relationships = $this->model->default_loaded_relationships;
        }else {
            //see if method exists
            if(method_exists($this->model, 'getModelRelationships')){
                $default_relationships = $this->model->getModelRelationships();
            }else {
                $default_relationships = [];
                //get all columns ending in id
                if(!isset($this->controller_data['columns']) && count($this->controller_data['columns'])  ){    
                    foreach($this->controller_data['columns'] as $k=>$v){
                        if(Str::endsWith($v['Field'], '_id')){
                            $default_relationships[] = Str::beforeLast($v['Field'], '_id');
                        }
                    }
                }
                ///LATER HERE ALSO CHECK ALL TABLE AND LOOK FOR $mode_id on them and look for pivtor tables with name of $model
                //and accordin create belongs to and belongs to many relationships.
            }
            //also include the relationship of those models
            foreach($default_relationships as $r){
                $model_name = 'App\Models\\' .  Str::singular(ucfirst($r)) ;
                try { 
                    $child_model = new $model_name; 
                    if(method_exists($child_model, 'getModelRelationships')){
                        foreach($child_model->getModelRelationships() as $c){
                            $default_relationships[] = $r.'.'.$c;
                        }
                    }
                } catch (\Throwable $e) {
                    // Ignore methods that throw errors (non-relationship methods)
                }
            }

            $this->model->default_loaded_relationships = $default_relationships;
        } 

         
         
        if( !is_iterable($with) & $with!==NULL){ 
            abort(500, 'with parameter must be an array');
        }
        //samfe for field
        //$fields = array_merge($this->model->index_relationships_columns, request()->input('fields', []));
        if(!isset($_GET['fields'])) {
            $_GET['fields'] = [];
        }
       
        //dd($this->model->index_relationships_columns);
        // dd(request()->fields);
        
        $q = $this->model->select( [ "*" ]);
        
        if(isset($this->model->default_loaded_relationships)){ 
            foreach($this->model->default_loaded_relationships as $i){ 
                $q->with([
                    $i => function ($query) use($i){ 
                        // Only select specific columns for the relationship (if needed)
                        $query->select( isset($this->model->index_relationships_columns[$i])?$this->model->index_relationships_columns[$i]:'*');  // Adjust to the columns you want for the related model
                    }
                ]);
            }
        }
         
        
        if($with!==NULL){
            foreach($with as $i){
                if($i!==NULL && isset( $this->model->index_relationships_columns[$i] )){
                    $q->with([
                        $i => function ($query) use($i){
                            // Only select specific columns for the relationship (if needed)
                            $query->select( $this->model->index_relationships_columns[$i]);  // Adjust to the columns you want for the related model
                        }
                    ]);
                }
            }
        }

        //where search term
        if($search!==NULL && $search_fields!==NULL){
            $q->where(function($query) use($search, $search_fields){
                foreach($search_fields as $f){
                    $query->orWhere($f, 'LIKE', '%'.$search.'%');
                }
            });
        }
         
        //$q->orderBy($this->model->index_default_sort??'id', $order??'ASC');
         //   dd($this->model->get());
        //return the records either all or paginated
        
        //return as data
        $data =  $q->where("id", $this->model->id )->get();
    

        
        $db_columns= array_keys($data[0]->getAttributes());
         
        foreach($data as $d) {
            if(isset($this->model->hidden_columns)){
                foreach($this->model->hidden_columns as $c){
                unset($d->{$c});
                }
            }
        } 
        
        foreach($data[0]->getRelations() as $k=>$rel) {
            if(is_iterable($rel) &&  count($rel)>0){  
                foreach($rel[0]->getAttributes() as $c=>$v) {
                    $db_columns[$k.'.'.$c] = $k.'.'.$c;
                }
            } elseif($rel!==NULL &&  method_exists($rel, 'getAttributes') ) { 
                foreach($rel->getAttributes() as $c=>$v) {
                    $db_columns[$k.'.'.$c] = $k.'.'.$c;
                }
            }
        }
        //dd($data[0]->getRelations()); 
        $this->db_columns = $db_columns;
         
        $this->filters=[
            'with' =>$with,
            'sort' =>$sort,
            'order' =>$order,
            'limit' =>$limit,
            'page' =>$page,
            'search' =>$search,
            'search_fields' =>$search_fields,
        ];
 
        $this->data = $data;
        $this->model = $data[0];
        return $data;
    }
 
    //LETS MOVE THAT STUFF FROM RAZOR HERE SEE BELOW FUNCTIONS menu_*

    public static function menu_lu_models()
    {
        return self::make_menu(  self::lu_mode_structure_helper( LuModel::getLuModels() ) );
    }

    public static function menu_non_lu_models()
    {
        return self::make_menu(  self::lu_mode_structure_helper( LuModel::getNonLuModels() ) );
    }

    public static function menu_models()
    {
        return self::make_menu(  self::lu_mode_structure_helper( LuModel::getModels() ));
    }

    public static function menu_structure_helper($iterable){ 
        return $iterable; //this is all to have control later if needed
    }

    public static function menu_make($iterable) {
        //return rendered view
        $html = view('lu_razor::'.self::getUI().'.menu', ['iterable' => $iterable])->render();
        return $html;
    }

    public static function fromData($action, $service, $model, $data, $controller_type) {
 
        //turn model to the child model it always was
         
        $action_folder = $model->action_folder ?? 'CRUD';
        // Fire the Actions event to handle state change
        if(!class_exists('\App\LuModels\Actions\\'.$action_folder.'\\'.ucfirst($action).ucfirst(class_basename($model)))) {
            $action = "\All1\LuModels\Actions\\".ucfirst($action);
        }else { //run that action
            $action = '\App\Models\Actions\\'.ucfirst($action).ucfirst(class_basename($model));
            
        }
        return $action::run($service,$model, $data, $controller_type);
    }
 

    public static function can_public_access_model_for($model,$action){
        $public_pages = $model->public_pages??[];
        $config_access = false;
        $model_definition_access = false; 
        $lu_config_public_models = config("lu::models.public_".$action."_models");
         
        //get clean class name without any starting backslashes
        $clean_class_name = ltrim(get_class($model), '\\');
        foreacH($lu_config_public_models as $k=>$v){
            //fix for a common mistake I make / instead of \
            $v = str_replace("/", "\\", $v);
            //fix for root \ in front of class name
            $v = ltrim($v, '\\');
            $lu_config_public_models[$k] = $v;
        }
         
        //dd(get_class($model),$lu_config_public_models);
        if(in_array($clean_class_name, $lu_config_public_models)) {
            $config_access = true;      
        }
        if( in_array( $action, $public_pages) ) {
            $model_definition_access = true;
        }
        if($model_definition_access) {
            return true;
        }
        if($config_access) {
            //make surce the model is just not set and is the default empty array
            if(!isset($public_pages) || $public_pages==[]) {
                return true;
            }
        }
         
        return false;
    }


    public static function authorize($action,$model, $data, $controller_type) {
        
        $is_public = \All1\LuModels\Services\LuModels::can_public_access_model_for($model, $action);
        
        $policy_access = auth()->check() && auth()->user()->can($action, $model, $data, $controller_type);
        // dd("ADASDS",$action);
        if(!$is_public && !$policy_access) {
            abort(403, 'Unauthorized! '.(auth()->check()?'Your Account':'The Public').' does not have permission to '.$action.
            ' this '.class_basename($model).' page.');
        }
         
    } 


    public function fireVerbs($action, $service,$model,$data, $controller_type) {
        
        // Fire the Verbs event to handle state change
        if(class_exists('\App\Models\Verbs\\'.$model->action_folder.'\\'.ucfirst(class_basename($model)).\Str::studly($action) ) ) {
            $action = '\App\Models\Verbs\\'.$model->action_folder.'\\'.ucfirst(class_basename($model)).\Str::studly($action);
        }else { //run that action
            $action = "\All1\LuModels\Verbs\\".\Str::studly($action); 
        } 
        $event = new $action($service, $model, $data , $controller_type);
        
        $service =    $event->handle();
        
        event(strtolower(class_basename($model)).'.updated', ['model'=>$model,'data' => $data, 'controller_type'=>$controller_type]); //<-- THIS IS THE EVENT YOU WANT TO LISTEN FOR IF USING LARAVEL BUILT-IN EVENTS
        
        return $service;
    }
}
