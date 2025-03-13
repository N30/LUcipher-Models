<?php

namespace All1\LuModels\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use ReflectionClass;
use ReflectionMethod;
use Str;

class LuModel extends Model
{

    public $index_columns = ['id', 'created_at' ];

    //these are removed from the API and also views from display
    public $hidden_columns = [  ];
    //Reminder that I am making this very unsecure specially with the modified query builder that allows all fields
    //so take necessary messures to secure this when and if it requires building models that are not read all and have sensitive columns    
    //you can defined for all the xtra joins
    public $index_relationships_columns = [
        /*  'relationship_name' => ['table_name.id', 'table_name.column_name'], and so on next row */
    ];

    public $default_search_columns;

    //default index sort column
    public $index_default_sort = 'id';
    public $index_default_order = 'DESC';

    //if not defined all relationships will be allowed as LuModel will get all 2 depth relationships
    //by detaul added, you can add as many as you want but for security reasons only ones whitelisted in index_columns show in resutls
    public $default_loaded_relationships; 

 //needed for API assignment of these fields/whatever user can manipulate
    public $action_folder = "Crud";

    public function displayText($column) {
        $data = $this->$column;
        if(is_iterable($data)) {
            //later this needs to be more presentable
            //return json_encode($data);
            return $data;
        }else {
            return $data;
        }
    }

    public function getModelRelationships()
    {
        $model = $this;
        $relationships = [];

        $reflector = new ReflectionClass($model);

        foreach ($reflector->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            // Only check methods defined in the model (not inherited)
            if ( $method->class === get_class($model) && $method->getNumberOfParameters() === 0) {
                try {
                    $result = $method->invoke($model);

                    if ($result instanceof Relation) {
                        $relationships[] = $method->getName();
                    }
                } catch (\Throwable $e) {
                    // Ignore methods that throw errors (non-relationship methods)
                }
            }
        }

        return $relationships;
    }

    public static function getModels() {

        $modelPath = app_path('Models');
        $models = collect(\File::allFiles($modelPath))
            ->map(fn($file) => self::getNamespaceFromFile($file ).'\\'.pathinfo($file, PATHINFO_FILENAME ))
            ->all();
        foreach ($models as $model) {

            $meta = self::getMetaData($model);
            if(isset($meta['abstract']) && $meta['abstract']) {
                continue;
            }
            $responce[$model] = $meta;
        }

        return $responce;
    }

    public static function getNamespaceFromFile($file) {
        $fileContents = file_get_contents($file);
        if (preg_match('/namespace\s+([a-zA-Z0-9_\\\]+)/', $fileContents, $matches)) {
            return $matches[1]; // Return the matched namespace
        }
        return null; // Return null if no namespace is found
    }

    public static function getLuModels() {
        $responce = self::getModels();
        foreach($responce as $key=>$model){
            if( $model['extends_lu_model']) {
                unset($responce[$key]);
            }
        }
        return $responce;
    }

    public static function getNonLuModels() {
        $responce = self::getModels();
        foreach($responce as $key=>$model){
            if(! $model['extends_lu_model']) {
                unset($responce[$key]);
            }
        }
        return $responce;
    }

    public static function getMetaData($model) {

        $tmp = explode("\\", $model);
            $relative_namespace= implode("\\", array_slice($tmp, 2));

            $model_name =   class_basename($model);
            $model_object = new $model;
            //make model name lower case and only alphanumberic
            $check = strtolower(str_replace(' ', '', ucwords(preg_replace('/[^A-Za-z0-9]/', ' ', $model_name))));

            if( in_array($check, ['lumodel', 'base']))  {
                return  [
                    'namespace'=>$model,
                    'obj'=> new $model_object,
                    'abstract'=>true
                ];
            }

            //place space in camel format string
            $friendly_name = preg_replace('/\B([A-Z])/', ' $1', $model_name);


            $implements_lumodel = (is_subclass_of($model_object, LuModel::class));
            $table = $model_object->getTable();
            $route_group = str_replace('wozxzow','~', \Str::slug( str_replace('\\','WOZXZOW',$relative_namespace)));
            $view_folder = str_replace('\-','.',Str::kebab($relative_namespace));
            //$routeName = 'lu::web.resources'; // Example: 'users.index'

            $routes = [];
            foreach(['web','api','spa','rest'] as $route_type){

                foreach(['index', 'create', 'store', 'show', 'edit', 'update', 'destroy'] as $action){
                    $route = route('lu::'.$route_type.'.resources', ['model'=>$route_group, 'action'=>$action]);

                    $routes[$route_type][$action] = [
                        'url'=>$route,
                        'method'=> match($action){
                            'index'=>'GET',
                            'create'=>'GET',
                            'store'=>'POST',
                            'show'=>'GET',
                            'edit'=>'GET',
                            'update'=>'PUT',
                            'destroy'=>'DELETE',
                        },
                        'dynamic'=> match($action){
                            'show'=>true,
                            'edit'=>true,
                            'update'=>true,
                            'destroy'=>true,
                            default=>false
                        }
                    ];
                }
            }

            return [
                'namespace'=>$model,
                'obj'=> new $model_object,
                'table'=>$table,
                'name'=>$friendly_name,
                'headline'=>Str::headline($friendly_name),
                'camel'=>Str::camel($friendly_name),
                'routes'=> $routes,
                'route_group'=>$route_group,
                'view_folder'=>$view_folder,
                'extends_lu_model'=>$implements_lumodel
            ];
    }

    public function createFromData($data) {
        //chec if apllication has its on create action class in \Appl\Models\Action with name CreateModel
        //dd('\App\Models\Actions\\'.$this->action_folder.'\Create'.ucfirst(class_basename($this)));
        if(!class_exists('\App\Models\Actions\\'.$this->action_folder.'\Create'.ucfirst(class_basename($this)))) {
            return \All1\LuModels\Actions\Create::run($data);
        }else { //run that action
            $action = '\App\Models\Actions\Create'.ucfirst(class_basename($this));
            return $action::run($data);
        }
    }
    
    public function updateFromData($data) {
        //chec if apllication has its on create action class in \Appl\Models\Action with name CreateModel
        //dd('\App\Models\Actions\\'.$this->action_folder.'\Create'.ucfirst(class_basename($this)));
        if(!class_exists('\App\Models\Actions\\'.$this->action_folder.'\Update'.ucfirst(class_basename($this)))) {
            return \All1\LuModels\Actions\Update::run($data);
        }else { //run that action
            $action = '\App\Models\Actions\Update'.ucfirst(class_basename($this));
            return $action::run($data);
        }
    }

    public function deleteFromData($data) {
        //chec if apllication has its on create action class in \Appl\Models\Action with name CreateModel
        //dd('\App\Models\Actions\\'.$this->action_folder.'\Create'.ucfirst(class_basename($this)));
        if(!class_exists('\App\Models\Actions\\'.$this->action_folder.'\Delete'.ucfirst(class_basename($this)))) {
            return \All1\LuModels\Actions\Delete::run($data);
        }else { //run that action
            $action = '\App\Models\Actions\Delete'.ucfirst(class_basename($this));
            return $action::run($data);
        }
    }       

    public function readFromData($data) {
        //chec if apllication has its on create action class in \Appl\Models\Action with name CreateModel
        //dd('\App\Models\Actions\\'.$this->action_folder.'\Create'.ucfirst(class_basename($this)));
        if(!class_exists('\App\Models\Actions\\'.$this->action_folder.'\Read'.ucfirst(class_basename($this)))) {
            return \All1\LuModels\Actions\Read::run($data);
        }else { //run that action
            $action = '\App\Models\Actions\Read'.ucfirst(class_basename($this));
            return $action::run($data);
        }
    }
}
