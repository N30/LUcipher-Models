<?php

namespace All1\LuModels\Services;

use All1\LuModels\Actions\Create;
use All1\LuModels\Actions\Delete;
use All1\LuModels\Actions\Index;
use All1\LuModels\Actions\Read;
use All1\LuModels\Actions\Update;
use All1\LuModels\Models\LuModel;
use All1\LuModels\Support\Exposure;
use All1\LuModels\Support\FieldVisibility;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Str;

class LuModels
{
    public $controller_data = [];

    public $model;

    public $type;

    public $data;

    public $db_columns;

    public $filters = [];

    public $controller_type;

    // force_model is for livewire initiliing when there is no request()->reoute('model')
    public function controller_initialize($type = 'api', $force_model = null)
    {

        $this->type = $type;
        $this->controller_data['action'] = request()->route('action') ?? 'index'; // isset($input[2])? $input[2] : 'index';
        $model_variable = $force_model ?? request()->route('model');

        if (! in_array($this->controller_data['action'], ['exception_actions_that_dont_need_model', 'another_one'])) {

            // get the model name from the first folder in the url

            $tmp = str_replace('~', '\\-', $model_variable);
            // turn $model to from reout group name to model name
            $relative_namespace = Str::studly(Str::singular($tmp));

            $this->controller_data['model_name'] = class_basename($relative_namespace);

            $this->controller_data['route_prefix'] = $model_variable;

            $model_full_namespace = 'App\Models\\'.ucfirst($relative_namespace);

            // check if the model exists

            if (! class_exists($model_full_namespace)) {
                // sometimes things that end in 'es' removes the es but the e is from the word, like hoe hence check
                if (class_exists('App\Models\\'.ucfirst($this->controller_data['model_name']).'e')) {
                    $model_full_namespace = 'App\Models\\'.ucfirst($this->controller_data['model_name']).'e';
                } else {
                    abort(404, Str::headline($relative_namespace).' is not a thing, no route found');
                }
            }

            // Safe by default: a model nobody exposed on this transport is a 404, like a model that
            // does not exist (see Support\Exposure).
            if (! Exposure::isExposed($model_full_namespace, $this->type)) {
                abort(404, Str::headline($relative_namespace).' is not a thing, no route found');
            }

            $this->model = new $model_full_namespace;

            $this->controller_data['meta'] = LuModel::getMetaData($model_full_namespace);

            // $this->controller_data['columns'] = \Schema::getColumnListing($this->controller_data['model->getTable()) ;
            $this->controller_data['columns'] = self::schemaColumns($this->model->getTable(), $this->model->getConnectionName());

            $sample = null;
            if (in_array($this->controller_data['action'], ['create', 'edit', 'need_sample'])) {
                $sample = $this->model->first();
            }

            foreach ($this->controller_data['columns'] as $k => $c) {
                $this->controller_data['columns'][$k] = (array) $c;
                $clean_type = preg_replace('/\s*\([^)]*\)/', '', $c->Type);
                $max_length = preg_replace('/[^0-9]/', '', $c->Type);
                $input_type = match ($clean_type) {
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
                $this->controller_data['columns'][$k]['description'] = $c->Comment == null ? '' : $c->Comment.' ,';
                $this->controller_data['columns'][$k]['description'] = $this->controller_data['columns'][$k]['description'].('Default: '.($c->Default == null ? 'NULL' : $c->Default));
                $this->controller_data['columns'][$k]['required'] = $c->Null == 'NO' ? true : false;
                $this->controller_data['columns'][$k]['clean_type'] = $clean_type;
                $this->controller_data['columns'][$k]['input_type'] = $input_type;
                $this->controller_data['columns'][$k]['max_length'] = $max_length ?? 524288;
                $this->controller_data['columns'][$k]['sample'] = $sample == null ? '' : $sample->{$c->Field};
            }

        }

        return $this->controller_data;
    }

    /**
     * Describe a table in the shape `SHOW FULL COLUMNS` used to return (Field, Type, Null, Key,
     * Default, Extra, Comment), read through the portable schema builder so every driver works.
     *
     * @return array<int, object>
     */
    public static function schemaColumns(string $table, ?string $connection = null): array
    {
        $schema = Schema::connection($connection);
        $primary = [];
        $unique = [];
        foreach ($schema->getIndexes($table) as $index) {
            if ($index['primary']) {
                $primary = array_merge($primary, $index['columns']);
            } elseif ($index['unique'] && count($index['columns']) === 1) {
                $unique[] = $index['columns'][0];
            }
        }

        $columns = [];
        foreach ($schema->getColumns($table) as $column) {
            $columns[] = (object) [
                'Field' => $column['name'],
                'Type' => $column['type'],
                'Null' => $column['nullable'] ? 'YES' : 'NO',
                'Key' => in_array($column['name'], $primary, true) ? 'PRI' : (in_array($column['name'], $unique, true) ? 'UNI' : ''),
                'Default' => $column['default'],
                'Extra' => $column['auto_increment'] ? 'auto_increment' : '',
                'Comment' => $column['comment'] ?? '',
            ];
        }

        return $columns;
    }

    public function controller_index($model)
    {
        $this->model = $model;
        $perpage = request()->input('per_page', 15);
        $filters = request()->input('filters', null);
        $with = request()->input('with', null);
        $select = request()->input('select', null);
        $sort = request()->input('sort', null);
        $order = request()->input('order', null);
        $limit = request()->input('limit', null);
        $page = request()->input('page', null);
        $search = request()->input('search', null);
        $search_fields = request()->input('search_fields', $this->model->default_search_columns ?? null);

        // determine method paginate/get
        if (request()->has('limit')) {
            $pagination_method = false;
        } else {
            $pagination_method = true;
        }

        if ($this->model->default_loaded_relationships) {
            $default_relationships = $this->model->default_loaded_relationships;
        } else {
            // see if method exists
            if (method_exists($this->model, 'getModelRelationships')) {
                $default_relationships = $this->model->getModelRelationships();
            } else {
                $default_relationships = [];
                // get all columns ending in id
                if (! isset($this->controller_data['columns'])
                &&
                count($this->controller_data['columns'])) {
                    foreach ($this->controller_data['columns'] as $k => $v) {
                        if (Str::endsWith($v['Field'], '_id')) {
                            $default_relationships[] = Str::beforeLast($v['Field'], '_id');
                        }
                    }
                }
                // /LATER HERE ALSO CHECK ALL TABLE AND LOOK FOR $mode_id on them and look for pivtor tables with name of $model
                // and accordin create belongs to and belongs to many relationships.
            }
            // also include the relationship of those models
            foreach ($default_relationships as $r) {
                $model_name = 'App\Models\\'.Str::singular(ucfirst($r));
                try {
                    $child_model = new $model_name;
                    if (method_exists($child_model, 'getModelRelationships')) {
                        foreach ($child_model->getModelRelationships() as $c) {
                            $default_relationships[] = $r.'.'.$c;
                        }
                    }
                } catch (\Throwable $e) {
                    // Ignore methods that throw errors (non-relationship methods)
                }
            }
        }

        if (! is_iterable($with) & $with !== null) {
            abort(500, 'with parameter must be an array');
        }
        // samfe for field
        // $fields = array_merge($this->model->index_relationships_columns, request()->input('fields', []));
        if (! isset($_GET['fields'])) {
            $_GET['fields'] = [];
        }

        // dd($this->model->index_relationships_columns);
        // dd(request()->fields);

        $q = $this->model->select(['*']);

        if (isset($this->model->default_loaded_relationships)) {
            foreach ($this->model->default_loaded_relationships as $i) {
                $q->with([
                    $i => function ($query) use ($i) {
                        // Only select specific columns for the relationship (if needed)
                        $query->select(isset($this->model->index_relationships_columns[$i]) ? $this->model->index_relationships_columns[$i] : '*');  // Adjust to the columns you want for the related model
                    },
                ]);
            }
        }

        if ($with !== null) {
            foreach ($with as $i) {
                if ($i !== null && isset($this->model->index_relationships_columns[$i])) {
                    $q->with([
                        $i => function ($query) use ($i) {
                            // Only select specific columns for the relationship (if needed)
                            $query->select($this->model->index_relationships_columns[$i]);  // Adjust to the columns you want for the related model
                        },
                    ]);
                }
            }
        }

        // where search term
        if ($search !== null && $search_fields !== null) {
            $q->where(function ($query) use ($search, $search_fields) {
                foreach ($search_fields as $f) {
                    $query->orWhere($f, 'LIKE', '%'.$search.'%');
                }
            });
        }

        $q->orderBy($this->model->index_default_sort ?? 'id', $order ?? 'ASC');
        //   dd($this->model->get());
        // return the records either all or paginated
        if ($pagination_method) {
            $data = $q->paginate($perpage);
            $data->appends([
                'per_page' => $perpage,
                'filters' => $filters,
                'with' => $with,
                'sort' => $sort,
                'order' => $order,
            ]);
        } else {
            // return as data
            $data = $q->get();
        }

        // v3.0.1: an empty table used to 500 here ($data[0] on an empty result).
        $first = $data->first();
        $visibility = FieldVisibility::for($this->model);
        $read_action = $this->controller_data['action'] ?? 'index';
        if ($first === null) {
            $this->db_columns = $visibility->readable($this->model->getConnection()->getSchemaBuilder()->getColumnListing($this->model->getTable()), $read_action);
            $this->filters = [
                'with' => $with,
                'sort' => $sort,
                'order' => $order,
                'limit' => $limit,
                'page' => $page,
                'search' => $search,
                'search_fields' => $search_fields,
            ];
            $this->data = $data;

            return $data;
        }

        $db_columns = $visibility->readable(array_keys($first->getAttributes()), $read_action);

        // $hidden, hidden_columns and anything $lu_fields keeps from this action never read out
        foreach ($data as $d) {
            $d->makeHidden($visibility->hiddenFor($d, $read_action));
        }

        foreach ($first->getRelations() as $k => $rel) {
            if (is_iterable($rel) && count($rel) > 0) {
                foreach ($rel[0]->getAttributes() as $c => $v) {
                    $db_columns[$k.'.'.$c] = $k.'.'.$c;
                }
            } elseif ($rel !== null && method_exists($rel, 'getAttributes')) {
                foreach ($rel->getAttributes() as $c => $v) {
                    $db_columns[$k.'.'.$c] = $k.'.'.$c;
                }
            }
        }
        // dd($data[0]->getRelations());
        $this->db_columns = $db_columns;

        $this->filters = [
            'with' => $with,
            'sort' => $sort,
            'order' => $order,
            'limit' => $limit,
            'page' => $page,
            'search' => $search,
            'search_fields' => $search_fields,
        ];

        $this->data = $data;

        return $data;
    }

    public function controller_show($model)
    {
        $this->model = $model;

        $filters = request()->input('filters', null);
        $with = request()->input('with', null);
        $select = request()->input('select', null);
        $sort = request()->input('sort', null);
        $order = request()->input('order', null);
        $limit = request()->input('limit', null);
        $page = request()->input('page', null);
        $search = request()->input('search', null);
        $search_fields = request()->input('search_fields', $this->model->default_search_columns ?? null);

        if ($this->model->default_loaded_relationships) {
            $default_relationships = $this->model->default_loaded_relationships;
        } else {
            // see if method exists
            if (method_exists($this->model, 'getModelRelationships')) {
                $default_relationships = $this->model->getModelRelationships();
            } else {
                $default_relationships = [];
                // get all columns ending in id
                if (! isset($this->controller_data['columns']) && count($this->controller_data['columns'])) {
                    foreach ($this->controller_data['columns'] as $k => $v) {
                        if (Str::endsWith($v['Field'], '_id')) {
                            $default_relationships[] = Str::beforeLast($v['Field'], '_id');
                        }
                    }
                }
                // /LATER HERE ALSO CHECK ALL TABLE AND LOOK FOR $mode_id on them and look for pivtor tables with name of $model
                // and accordin create belongs to and belongs to many relationships.
            }
            // also include the relationship of those models
            foreach ($default_relationships as $r) {
                $model_name = 'App\Models\\'.Str::singular(ucfirst($r));
                try {
                    $child_model = new $model_name;
                    if (method_exists($child_model, 'getModelRelationships')) {
                        foreach ($child_model->getModelRelationships() as $c) {
                            $default_relationships[] = $r.'.'.$c;
                        }
                    }
                } catch (\Throwable $e) {
                    // Ignore methods that throw errors (non-relationship methods)
                }
            }

            $this->model->default_loaded_relationships = $default_relationships;
        }

        if (! is_iterable($with) & $with !== null) {
            abort(500, 'with parameter must be an array');
        }
        // samfe for field
        // $fields = array_merge($this->model->index_relationships_columns, request()->input('fields', []));
        if (! isset($_GET['fields'])) {
            $_GET['fields'] = [];
        }

        // dd($this->model->index_relationships_columns);
        // dd(request()->fields);

        $q = $this->model->select(['*']);

        if (isset($this->model->default_loaded_relationships)) {
            foreach ($this->model->default_loaded_relationships as $i) {
                $q->with([
                    $i => function ($query) use ($i) {
                        // Only select specific columns for the relationship (if needed)
                        $query->select(isset($this->model->index_relationships_columns[$i]) ? $this->model->index_relationships_columns[$i] : '*');  // Adjust to the columns you want for the related model
                    },
                ]);
            }
        }

        if ($with !== null) {
            foreach ($with as $i) {
                if ($i !== null && isset($this->model->index_relationships_columns[$i])) {
                    $q->with([
                        $i => function ($query) use ($i) {
                            // Only select specific columns for the relationship (if needed)
                            $query->select($this->model->index_relationships_columns[$i]);  // Adjust to the columns you want for the related model
                        },
                    ]);
                }
            }
        }

        // where search term
        if ($search !== null && $search_fields !== null) {
            $q->where(function ($query) use ($search, $search_fields) {
                foreach ($search_fields as $f) {
                    $query->orWhere($f, 'LIKE', '%'.$search.'%');
                }
            });
        }

        // $q->orderBy($this->model->index_default_sort??'id', $order??'ASC');
        //   dd($this->model->get());
        // return the records either all or paginated

        // return as data
        $data = $q->where('id', $this->model->id)->get();

        $visibility = FieldVisibility::for($this->model);
        $read_action = FieldVisibility::normalizeAction($this->controller_data['action'] ?? 'show');
        $db_columns = $visibility->readable(array_keys($data[0]->getAttributes()), $read_action);

        foreach ($data as $d) {
            $d->makeHidden($visibility->hiddenFor($d, $read_action));
        }

        foreach ($data[0]->getRelations() as $k => $rel) {
            if (is_iterable($rel) && count($rel) > 0) {
                foreach ($rel[0]->getAttributes() as $c => $v) {
                    $db_columns[$k.'.'.$c] = $k.'.'.$c;
                }
            } elseif ($rel !== null && method_exists($rel, 'getAttributes')) {
                foreach ($rel->getAttributes() as $c => $v) {
                    $db_columns[$k.'.'.$c] = $k.'.'.$c;
                }
            }
        }
        // dd($data[0]->getRelations());
        $this->db_columns = $db_columns;

        $this->filters = [
            'with' => $with,
            'sort' => $sort,
            'order' => $order,
            'limit' => $limit,
            'page' => $page,
            'search' => $search,
            'search_fields' => $search_fields,
        ];

        $this->data = $data;
        $this->model = $data[0];

        return $data;
    }

    // LETS MOVE THAT STUFF FROM RAZOR HERE SEE BELOW FUNCTIONS menu_*

    public static function menu_lu_models()
    {
        return self::make_menu(self::lu_mode_structure_helper(LuModel::getLuModels()));
    }

    public static function menu_non_lu_models()
    {
        return self::make_menu(self::lu_mode_structure_helper(LuModel::getNonLuModels()));
    }

    public static function menu_models()
    {
        return self::make_menu(self::lu_mode_structure_helper(LuModel::getModels()));
    }

    public static function menu_structure_helper($iterable)
    {
        return $iterable; // this is all to have control later if needed
    }

    public static function menu_make($iterable)
    {
        // return rendered view
        $html = view('lu_razor::'.self::getUI().'.menu', ['iterable' => $iterable])->render();

        return $html;
    }

    public static function fromData($action, $service, $model, $data, $controller_type)
    {

        // turn model to the child model it always was

        $action = self::actionClass($action, $model);

        return $action::run($service, $model, $data, $controller_type);
    }

    /**
     * The Action class that runs $action for $model: the app's override
     * App\LuModels\Actions\{action_folder}\{Action}{Model} (e.g. App\LuModels\Actions\CRUD\CreateUser)
     * when it exists, else the package default All1\LuModels\Actions\{Action}.
     *
     * v3.0.1: the name is built once. 3.0.0 checked App\LuModels\Actions\{folder}\… but then
     * instantiated App\Models\Actions\… (different root, folder dropped), so an override only ran
     * if the app defined both classes.
     */
    public static function actionClass($action, $model)
    {
        $action_folder = $model->action_folder ?? 'CRUD';
        $override = '\App\LuModels\Actions\\'.$action_folder.'\\'.ucfirst($action).ucfirst(class_basename($model));

        return class_exists($override) ? $override : "\All1\LuModels\Actions\\".ucfirst($action);
    }

    public static function can_public_access_model_for($model, $action)
    {
        $public_pages = $model->public_pages ?? [];
        $config_access = false;
        $model_definition_access = false;
        // the config keys are index/create/read/update/delete while the read action is called "show"
        $config_key = $action === 'show' ? 'read' : $action;
        $lu_config_public_models = array_merge(
            (array) config('lu::models.public_'.$config_key.'_models', []),
            $config_key === $action ? [] : (array) config('lu::models.public_'.$action.'_models', []),
        );

        // get clean class name without any starting backslashes
        $clean_class_name = ltrim(get_class($model), '\\');
        foreach ($lu_config_public_models as $k => $v) {
            // fix for a common mistake I make / instead of \
            $v = str_replace('/', '\\', $v);
            // fix for root \ in front of class name
            $v = ltrim($v, '\\');
            $lu_config_public_models[$k] = $v;
        }

        // dd(get_class($model),$lu_config_public_models);
        if (in_array($clean_class_name, $lu_config_public_models)) {
            $config_access = true;
        }
        if (in_array($action, $public_pages)) {
            $model_definition_access = true;
        }
        if ($model_definition_access) {
            return true;
        }
        if ($config_access) {
            // make surce the model is just not set and is the default empty array
            if ($public_pages == []) {
                return true;
            }
        }

        return false;
    }

    /**
     * The gate every action passes before it touches the model: the configured super-admin method
     * first, then an explicit public opt-in for this model and action, then the model's policy.
     * A signed-in user hitting a model with no policy is denied, not let through.
     */
    public static function authorize($action, $model, $data, $controller_type)
    {
        if (self::isSuperAdmin()) {
            return true;
        }

        if (LuModels::can_public_access_model_for($model, $action)) {
            return true;
        }

        if (! auth()->check()) {
            abort(403, 'Unauthorized! The Public does not have permission to '.$action.' this '.class_basename($model).' page.');
        }

        if (Gate::getPolicyFor($model) === null) {
            abort(403, 'No policy is registered for '.get_class($model).'. Define App\\Policies\\'.class_basename($model).'Policy (or register one with Gate::policy()) before exposing it.');
        }

        if (! auth()->user()->can($action, [$model, $data, $controller_type])) {
            abort(403, 'Unauthorized! Your Account does not have permission to '.$action.' this '.class_basename($model).' page.');
        }

        return true;
    }

    /** Whether the signed-in user bypasses policies through the configured super-admin method. */
    public static function isSuperAdmin(): bool
    {
        $method = config('lu::models.super_admin_method');

        return is_string($method) && $method !== ''
            && auth()->check()
            && method_exists(auth()->user(), $method)
            && auth()->user()->{$method}();
    }

    /**
     * Run the operation behind a legacy Verb name (Creation, Updation, Deletion, Revelation, Annexation)
     * without authorizing: the app's Verb override when one exists, else the Action's perform(), then
     * the string event and the audit row. Kept for callers of 3.0.x; Actions\Action::execute() is the
     * code.
     */
    public function fireVerbs($action, $service, $model, $data, $controller_type)
    {
        $class = self::actionForVerb($action);

        return (new $class)->execute($service, $model, (array) $data, $controller_type);
    }

    /** The Action class that owns a legacy Verb name; an unknown name maps to the Index operation. */
    public static function actionForVerb(string $verb): string
    {
        return match (strtolower(class_basename($verb))) {
            'creation', 'create', 'store' => Create::class,
            'updation', 'update' => Update::class,
            'deletion', 'delete', 'destroy' => Delete::class,
            'revelation', 'read', 'show' => Read::class,
            default => Index::class,
        };
    }

    /** Verb (or action) => the past tense used in the {model}.{past} string event. */
    public const STRING_EVENTS = [
        'creation' => 'created', 'create' => 'created', 'store' => 'created',
        'updation' => 'updated', 'update' => 'updated',
        'deletion' => 'deleted', 'delete' => 'deleted', 'destroy' => 'deleted',
    ];

    /**
     * The Laravel string event a verb fires, e.g. "user.created" for Creation on a User model;
     * null for the read-only verbs (Revelation, Annexation), which fire none.
     */
    public static function stringEventName($verb, $model)
    {
        $past = self::STRING_EVENTS[strtolower(class_basename((string) $verb))] ?? null;

        return $past === null ? null : strtolower(class_basename($model)).'.'.$past;
    }
}
