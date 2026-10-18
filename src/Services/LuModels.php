<?php

namespace All1\LuModels\Services;

use All1\LuModels\Actions\Create;
use All1\LuModels\Actions\Delete;
use All1\LuModels\Actions\Index;
use All1\LuModels\Actions\Read;
use All1\LuModels\Actions\Update;
use All1\LuModels\Models\LuModel;
use All1\LuModels\Support\Descriptor;
use All1\LuModels\Support\Exposure;
use All1\LuModels\Support\FieldVisibility;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

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

            // the columns come from the cached descriptor; only the placeholder sample is per request
            $this->controller_data['columns'] = Descriptor::for($model_full_namespace)->legacyColumns();

            if (in_array($this->controller_data['action'], ['create', 'edit', 'need_sample'])) {
                $sample = $this->model->first();
                foreach ($this->controller_data['columns'] as $k => $c) {
                    $this->controller_data['columns'][$k]['sample'] = $sample === null ? '' : ($sample->{$c['Field']} ?? '');
                }
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

    /** The listing: paginated unless the request asks for a flat `limit`. */
    public function controller_index($model)
    {
        $this->model = $model;

        return $this->runQuery($model, 'index', null, ! request()->has('limit'));
    }

    /** One record, by the key the controller already resolved onto $model. */
    public function controller_show($model)
    {
        $this->model = $model;
        $action = FieldVisibility::normalizeAction($this->controller_data['action'] ?? 'show');

        $data = $this->runQuery($model, $action, fn ($query) => $query->whereKey($model->getKey()), false);

        if ($data->isEmpty()) {
            abort(404, 'Record not found');
        }

        $this->model = $data->first();

        return $data;
    }

    /**
     * The one query behind index and show (3.0.x carried two copies that had already drifted):
     * eager loads from the model's own list or the descriptor's relationships (one level deeper
     * too), the request's `with`, a search over fields the action may read, an order the request
     * may set, pagination or a flat `limit`; then visibility on the rows and the column list.
     *
     * @param  (callable(Builder): mixed)|null  $scope
     */
    protected function runQuery(Model $model, string $read_action, ?callable $scope, bool $paginate)
    {
        $request = request();
        $visibility = FieldVisibility::for($model);
        $descriptor = Descriptor::for($model);

        $with = $request->input('with');
        if ($with !== null && ! is_array($with)) {
            abort(400, 'The with parameter must be an array.');
        }
        $default_order = strtolower((string) ($descriptor->options['index_default_order'] ?? 'asc'));
        $order = strtolower((string) $request->input('order', $default_order));
        $order = in_array($order, ['asc', 'desc'], true) ? $order : $default_order;
        $search = $request->input('search');
        $readable = $visibility->readable(array_keys($descriptor->fields), $read_action);
        $search_fields = array_values(array_intersect(
            (array) $request->input('search_fields', $descriptor->options['default_search_columns'] ?: $readable),
            $readable,
        ));

        $query = $model->newQuery()->select(['*']);

        foreach ($this->eagerLoads($model, $descriptor) as $relation) {
            $columns = $model->index_relationships_columns[$relation] ?? ['*'];
            $query->with([$relation => fn ($q) => $q->select($columns)]);
        }
        foreach ((array) $with as $relation) {
            if ($relation !== null && isset($model->index_relationships_columns[$relation])) {
                $columns = $model->index_relationships_columns[$relation];
                $query->with([$relation => fn ($q) => $q->select($columns)]);
            }
        }

        if ($search !== null && $search !== '') {
            if ($search_fields === []) {
                // the request named only fields this action may not read: nothing matches, nothing leaks
                $query->whereRaw('1 = 0');
            } else {
                $query->where(function ($q) use ($search, $search_fields) {
                    foreach ($search_fields as $field) {
                        $q->orWhere($field, 'LIKE', '%'.$search.'%');
                    }
                });
            }
        }

        if ($scope !== null) {
            $scope($query);
        } else {
            $query->orderBy($descriptor->options['index_default_sort'] ?? $model->getKeyName(), $order);
        }

        if ($paginate) {
            $per_page = (int) $request->input('per_page', 15);
            $data = $query->paginate($per_page > 0 && $per_page <= 200 ? $per_page : 15);
            $data->appends(array_filter(['per_page' => $request->input('per_page'), 'with' => $with, 'order' => $request->input('order'), 'search' => $search]));
        } else {
            $limit = (int) $request->input('limit', 0);
            $data = ($scope === null && $limit > 0 ? $query->limit($limit) : $query)->get();
        }

        $first = $data->first();
        $db_columns = $visibility->readable($first ? array_keys($first->getAttributes()) : array_keys($descriptor->fields), $read_action);

        foreach ($data as $row) {
            $row->makeHidden($visibility->hiddenFor($row, $read_action));
        }

        if ($first) {
            foreach ($first->getRelations() as $name => $related) {
                $sample = is_iterable($related) ? (count($related) > 0 ? $related[0] : null) : $related;
                if ($sample !== null && method_exists($sample, 'getAttributes')) {
                    foreach (array_keys($sample->getAttributes()) as $column) {
                        $db_columns[$name.'.'.$column] = $name.'.'.$column;
                    }
                }
            }
        }

        $this->db_columns = $db_columns;
        $this->filters = [
            'with' => $with,
            'sort' => $request->input('sort'),
            'order' => $request->input('order'),
            'limit' => $request->input('limit'),
            'page' => $request->input('page'),
            'search' => $search,
            'search_fields' => $search_fields,
        ];
        $this->data = $data;

        return $data;
    }

    /**
     * The relationships to eager load: the model's own $default_loaded_relationships, else every
     * relationship the descriptor found plus each one's own relationships (one level deeper).
     *
     * @return list<string>
     */
    protected function eagerLoads(Model $model, Descriptor $descriptor): array
    {
        if (! empty($model->default_loaded_relationships)) {
            return array_values((array) $model->default_loaded_relationships);
        }

        $relations = [];
        foreach ($descriptor->relationships as $name => $related) {
            $relations[] = $name;
            if ($related !== null && class_exists($related)) {
                foreach (array_keys(Descriptor::for($related)->relationships) as $child) {
                    $relations[] = $name.'.'.$child;
                }
            }
        }

        return $relations;
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
