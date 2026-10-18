<?php

namespace All1\LuModels\Http\Controllers\Api;

use All1\LuModels\Routing\ResourceRoutes;
use All1\LuModels\Services\LuModels;
use All1\LuModels\Support\FieldVisibility;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Str;
use Symfony\Component\HttpFoundation\Response;

// use \All1\LuModels\Services\LuModels;

class ApiResourceController
{
    public $model;

    public $route_prefix;

    public $aciton;

    public $model_name;

    public $meta;

    public $action;

    public $columns; // all columns of such type model from route slug

    public $db_columns; // all columns that were pulled buy query builder

    public $filters = [];

    public $service;

    public $controller_type;

    /* API DOCUMENTATION **

    // GET /api/index/{model} list all records
    //exampel: /api/index/events?limit=1&sort=title&order=DESC&filter=title:Prof.%20Richard%20Ondricka%20DDS&paginate=1&page=4
    // GET /api/show/{model}/{id} show a single record
    //example: /api/show/events/1

    */

    public $force_model;

    /** spl_object_id() of the request this instance was initialized for; another request re-initializes. */
    protected ?int $initializedForRequest = null;

    public function __construct($force_model = null)
    {
        $this->force_model = $force_model;
        $this->initialize();
    }

    /**
     * Read the current request: which model, which action, which transport, and the model's schema.
     * Runs in the constructor and again from prepare() when a different request reaches a cached
     * instance (Laravel caches controllers on the Route object, so feature tests that send several
     * requests and long-running workers hit the same instance).
     */
    protected function initialize(): void
    {
        $model = request()->route('model') ?? $this->force_model;
        $this->controller_type = explode('.', Str::after(request()->route()->getName(), 'lu::'))[0];
        $this->service = new LuModels;
        $controller_data = $this->service->controller_initialize($this->controller_type, $model);
        $this->model = $this->service->model;
        $this->route_prefix = $controller_data['route_prefix'];
        $this->action = request()->route('action') ?? 'index';
        $this->model_name = $controller_data['model_name'];
        $this->meta = $controller_data['meta'];
        $this->columns = $controller_data['columns'];
        $this->filters = [];
        $this->db_columns = null;
        $this->initializedForRequest = spl_object_id(request());
    }

    /** Re-initialize for a new request if needed, then refuse a mismatched HTTP method. */
    protected function prepare(Request $request): void
    {
        if ($this->initializedForRequest !== null && $this->initializedForRequest !== spl_object_id($request)) {
            $this->initialize();
        }

        $this->guardMethod($request);
    }

    /**
     * Display a listing of the resource.
     */
    public function __invoke(Request $request, $model = null, $action = null, $id = null)
    {
        return $this->toResponse($this->runAction($request, $id));
    }

    /** Guard the HTTP method, run the action and hand back its raw result: the data for reads, an array for writes. */
    protected function runAction(Request $request, $id)
    {
        $this->prepare($request);

        return $this->{$this->action}($id);
    }

    /**
     * The API transport's answer. A validation failure (including a guarded key in the payload) is
     * 422 with the errors by field, a missing record 404, everything else JSON with status 200.
     */
    protected function toResponse($result)
    {
        if ($result instanceof Response) {
            return $result;
        }

        if (is_array($result) && isset($result['errors'])) {
            $errors = $result['errors'] instanceof \Illuminate\Contracts\Validation\Validator
                ? $result['errors']->errors()->toArray()
                : (array) $result['errors'];

            return new JsonResponse(['message' => $result['message'] ?? 'The given data was invalid.', 'errors' => $errors], 422);
        }

        $status = 200;
        if (is_array($result) && isset($result['status'])) {
            $status = (int) $result['status'];
            unset($result['status']);
        }

        return new JsonResponse($result, $status);
    }

    /**
     * The validator for a write: the model's rules for the action plus a rejection of every client
     * key the model does not allow written (not fillable, guarded, or kept from this action by
     * $lu_fields). Rejecting beats silently dropping: the caller learns the contract.
     */
    protected function validatorFor(array $data, string $action, array $client_keys): \Illuminate\Contracts\Validation\Validator
    {
        $rules = $this->model->{$action === 'create' ? 'store_validation_rules' : 'update_validation_rules'} ?? [];
        $rejected = FieldVisibility::for($this->model)->rejectedWrites($client_keys, $action);

        return Validator::make($data, $rules)->after(function ($validator) use ($rejected) {
            foreach ($rejected as $key) {
                $validator->errors()->add($key, 'The '.$key.' field cannot be written.');
            }
        });
    }

    /**
     * v3.0.1: refuse an action reached through the wrong HTTP method (e.g. GET .../destroy/5),
     * and any path segment that is not one of the seven resource actions. The routes are
     * method-scoped already (All1\LuModels\Routing\ResourceRoutes); this keeps an app that
     * points its own Route::any at these controllers from reopening the hole.
     */
    protected function guardMethod(Request $request): void
    {
        if (! ResourceRoutes::allows($request->method(), $this->action)) {
            abort(405, 'The '.$request->method().' method is not allowed for the '.$this->action.' action.');
        }
    }

    /**
     * v3.0.1: the record a write action produced. The package Verbs return the service (its
     * ->model is the record); an app's own Action or Verb override may still return the model
     * itself. Anything else falls back to the model the controller looked up.
     */
    public static function recordOf($result, $fallback = null)
    {
        if ($result instanceof Model) {
            return $result;
        }
        if (is_object($result) && isset($result->model) && $result->model instanceof Model) {
            return $result->model;
        }

        return $fallback;
    }

    public function create()
    {
        // nothing for API other controller types will override
    }

    public function index($a = null)
    {
        $this->service = LuModels::fromData('index', $this->service, $this->model, request()->all(), $this->controller_type);
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
        // if id is in encrypted format decrypt
        if (strlen($id) > 20 && ! is_numeric($id)) {
            $id = decrypt($id);
            $passed_id_type = 'encrypted';
        } elseif (is_numeric($id)) {
            $id = (int) $id;
            $passed_id_type = 'plain';
            if (! auth()->check() && $this->model->allow_public_access_by_id == false) {
                abort(403, 'You must be authenticated by API or login in order to view this record through this URL');
            }
        } else {
            $passed_id_type = 'slug';
            if (! auth()->check() && $this->model->allow_public_access_by_slug == false) {
                abort(403, 'You must be authenticated by API or login in order to view this record through this URL');
            }
        }
        if ($passed_id_type !== 'encrypted' && $this->model->allow_any_unencrypred_access == false) {
            abort(403, 'You must be authenticated by API or login in order to view this record through this URL');
        }

        if ($passed_id_type == 'slug') {
            $this->model = $this->model->where('slug', $id)->first();
        } else {
            $this->model = $this->model->find($id);
        }
        if (! $this->model) {
            abort(404, 'Record not found');
        }

        $this->service = LuModels::fromData('read', $this->service, $this->model, request()->all(), $this->controller_type);
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
        $this->model = $this->model->find($id);
        if (! $this->model) {
            abort(404, 'Record not found');
        }

        if (method_exists($this->model, 'readFromData')) {

            $this->service = $this->model->fromData('read', request()->except(['_token', '_method']));

        } else {
            $this->service = LuModels::fromData('read', $this->service, $this->model, request()->all(), $this->controller_type);
        }
        $this->model = $this->service->model;

        return $this->model;
    }

    public function store($data = null)
    {

        if (! isset($data)) {
            $data = request()->except(['_token', '_method']);
        }
        $client_keys = array_keys($data);

        foreach ($this->columns as $k => $v) {
            if ($v['Key'] == 'PRI' && $v['Extra'] == 'auto_increment') {
                unset($this->columns[$k]);
            }
            if ($v['Field'] == 'ip_address') {
                $data = array_merge($data, ['ip_address' => request()->ip()]);
            } elseif ($v['Field'] == 'user_id') {
                $data = array_merge($data, ['user_id' => auth()->id()]);
            } elseif ($v['Field'] == 'user_agent') {
                $data = array_merge($data, ['user_agent' => request()->header('User-Agent')]);
            }
        }
        // opinionated type hinting LuModel rule:
        // foreach session starting with {model}_ that exists in db table but not in submissions add to attributesafterAll(function () {
        foreach ((session()->all()) as $k => $v) {
            if (Str::startsWith($k, strtolower($this->model_name).'_') && ! array_key_exists($k, $data)) {
                // check it exists in db columns
                $exists = false;
                $pure_name = Str::after($k, strtolower($this->model_name).'_');
                foreach ($this->columns as $col) {
                    if ($col['Field'] == $pure_name) {
                        $data = array_merge($data, [$pure_name => $v]);
                    }
                }
            }
        }

        // see if model has slug by checking if it has method getSlugOptions
        if (method_exists($this->model, 'getSlugOptions')) {
            $slug_col = $this->model->getSlugOptions()->generateSlugFrom[0];
            $slug = Str::slug(request()->$slug_col);
            $data = array_merge($data, ['slug' => $slug]);
        }

        $validator = $this->validatorFor($data, 'create', $client_keys);

        if ($validator->fails()) {
            return ['errors' => $validator, 'message' => 'There was an issue with your input.'];
        }

        // dd($validated);
        // pass data and controller_type web, api, spa, etc.... (for record keeping)
        $model_copy_since_method_exist_is_mutating_it = $this->model;
        if (method_exists($this->model, 'createFromData')) {
            $data = $this->model->fromData('create', $this->service, $data, $this->controller_type);
        } else {
            $data = LuModels::fromData('create', $this->service, $model_copy_since_method_exist_is_mutating_it, $data, $this->controller_type);
        }
        // the encrypted key of the record the write produced (an app override may return the model itself)
        $record = self::recordOf($data);
        $id = $record ? encrypt($record->getKey()) : null;

        return ['message' => 'We have received and saved your information successfully.', 'data' => $data, 'id' => $id];
    }

    public function update($id, $data = null)
    {
        if (! isset($data)) {
            $data = request()->except(['_token', '_method']);
        }

        $obj = $this->model->find($id);
        if (! $obj) {
            return ['message' => 'Record not found', 'data' => [], 'status' => 404];
        }

        $validator = $this->validatorFor($data, 'update', array_keys($data));
        if ($validator->fails()) {
            return ['errors' => $validator, 'message' => 'There was an issue with your input.'];
        }

        if (method_exists($obj, 'updateFromData')) {
            $data = $obj->fromData('update', $data);
        } else {
            $data = LuModels::fromData('update', $this->service, $obj, $data, $this->controller_type);
        }

        // v3.0.1: the Verbs now return the service; 'data' stays the updated model, as in 3.0.0.
        return ['message' => 'Record updated', 'data' => self::recordOf($data, $obj)];
    }

    public function destroy($id)
    {
        // check if auth user owns the record
        // if not abort
        // if yes delete
        $obj = $this->model->find($id);
        if (! $obj) {
            return ['message' => 'Record not found', 'data' => [], 'status' => 404];
        }
        // check if auth user owns the record
        // if not abort
        // if yes delete

        if (method_exists($obj, 'deleteFromData')) {
            $data = $obj->fromData('delete', request()->except(['_token', '_method']));
        } else {
            $data = LuModels::fromData('delete', $this->service, $obj, request()->except(['_token', '_method']), $this->controller_type);
        }

        // v3.0.1: 'data' is the deleted model (3.0.0 fataled before reaching here).
        return ['message' => 'Record deleted', 'data' => self::recordOf($data, $obj)];

    }
}
