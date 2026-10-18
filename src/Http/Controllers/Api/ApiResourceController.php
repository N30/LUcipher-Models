<?php

namespace All1\LuModels\Http\Controllers\Api;

use All1\LuModels\Routing\ResourceRoutes;
use All1\LuModels\Services\LuModels;
use All1\LuModels\Support\Descriptor;
use All1\LuModels\Support\FieldVisibility;
use All1\LuModels\Support\Result;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
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

    /** Request keys that are never model input: the CSRF token, the method spoof and the dry-run switch. */
    public const NOT_INPUT = ['_token', '_method', 'dry_run'];

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
    public function __invoke(Request $request)
    {
        return $this->toResponse($this->runAction($request, $request->route('id')));
    }

    /** Guard the HTTP method, run the action and hand back its raw result: the data for reads, an array for writes. */
    protected function runAction(Request $request, $id)
    {
        $this->prepare($request);

        return $this->{$this->action}($id);
    }

    /**
     * The API transport's answer: a Result as JSON with its status (422 with errors by field, 404
     * for a missing record, 200 otherwise); a read in one envelope: `{data, meta, links}` for a page,
     * `{data, meta: {count}}` for a flat list, `{data}` for one record.
     */
    protected function toResponse($result)
    {
        if ($result instanceof Result) {
            return $result->toJsonResponse();
        }

        if ($result instanceof Response) {
            return $result;
        }

        if ($result instanceof Paginator) {
            $total = $result instanceof LengthAwarePaginator;

            return new JsonResponse([
                'data' => $result->items(),
                'meta' => [
                    'current_page' => $result->currentPage(),
                    'from' => $result->firstItem(),
                    'to' => $result->lastItem(),
                    'per_page' => $result->perPage(),
                    'last_page' => $total ? $result->lastPage() : null,
                    'total' => $total ? $result->total() : null,
                    'path' => $result->path(),
                ],
                'links' => [
                    'first' => $result->url(1),
                    'last' => $total ? $result->url($result->lastPage()) : null,
                    'prev' => $result->previousPageUrl(),
                    'next' => $result->nextPageUrl(),
                ],
            ], 200);
        }

        if ($result instanceof Collection) {
            return new JsonResponse(['data' => $result->values(), 'meta' => ['count' => $result->count()]], 200);
        }

        return new JsonResponse(['data' => $result], 200);
    }

    /** Whether this transport honours `dry_run`: the API does; a browser form has nowhere to show a conditional. */
    protected function supportsDryRun(): bool
    {
        return true;
    }

    protected function dryRun(): bool
    {
        return $this->supportsDryRun() && request()->boolean('dry_run');
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
     * The record $id names. An encrypted key always works; a plain key or a slug works for a signed-in
     * caller (the policy decides what they may reach) and for a guest only where the model opts in
     * ($allow_public_access_by_id, $allow_public_access_by_slug, or $allow_any_unencrypted_access for
     * both). 3.0.x refused plain keys for everyone unless the model opted in, which kept the shipped
     * UI's own links from reaching the pages they pointed at. Null when no record matches.
     */
    protected function findRecord($id, bool $guestOptIns = false): ?Model
    {
        [$kind, $key] = $this->resolveKey($id);

        if ($guestOptIns && $kind !== 'encrypted' && ! auth()->check() && ! $this->guestMayUse($kind)) {
            abort(403, 'Sign in, or use the encrypted id, to reach this record through this URL.');
        }

        return $kind === 'slug'
            ? $this->model->newQuery()->where('slug', $key)->first()
            : $this->model->newQuery()->find($key);
    }

    /**
     * What kind of key the request sent, and the key itself: a number is the plain key, a value
     * that decrypts is an encrypted key, anything else is a slug when the model has one.
     *
     * @return array{0: string, 1: mixed}
     */
    protected function resolveKey($id): array
    {
        if ($id === null || is_numeric($id)) {
            return ['plain', $id];
        }

        try {
            return ['encrypted', decrypt($id)];
        } catch (DecryptException) {
            $has_slug = Descriptor::for($this->model)->field('slug') !== null;

            return [$has_slug ? 'slug' : 'plain', $id];
        }
    }

    /** Whether a guest may name a record by this kind of key: the model's own public opt-ins. */
    protected function guestMayUse(string $kind): bool
    {
        $model = $this->model;
        if (($model->allow_any_unencrypted_access ?? false) || ($model->allow_any_unencrypred_access ?? false)) {
            return true;
        }

        return (bool) ($kind === 'slug' ? ($model->allow_public_access_by_slug ?? false) : ($model->allow_public_access_by_id ?? false));
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
        $this->model = $this->findRecord($id, guestOptIns: true) ?? abort(404, 'Record not found');

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

        return $this->model;
    }

    public function edit($id)
    {
        $this->model = $this->findRecord($id, guestOptIns: true) ?? abort(404, 'Record not found');

        $this->service = LuModels::fromData('read', $this->service, $this->model, request()->all(), $this->controller_type);
        $this->model = $this->service->model;

        return $this->model;
    }

    public function store($data = null)
    {

        if (! isset($data)) {
            $data = request()->except(self::NOT_INPUT);
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
            return Result::invalid($validator);
        }
        if ($this->dryRun()) {
            return LuModels::wouldDo('create', $this->service, $this->model, $data, $this->controller_type);
        }

        // dd($validated);
        // pass data and controller_type web, api, spa, etc.... (for record keeping)
        $data = LuModels::fromData('create', $this->service, $this->model, $data, $this->controller_type);
        // the record the write produced (an app override may return the model itself)
        $record = self::recordOf($data);

        return Result::ok('We have received and saved your information successfully.', $record, $record, $record ? encrypt($record->getKey()) : null);
    }

    public function update($id, $data = null)
    {
        if (! isset($data)) {
            $data = request()->except(self::NOT_INPUT);
        }

        $obj = $this->findRecord($id);
        if (! $obj) {
            return Result::notFound();
        }

        $validator = $this->validatorFor($data, 'update', array_keys($data));
        if ($validator->fails()) {
            return Result::invalid($validator);
        }
        if ($this->dryRun()) {
            return LuModels::wouldDo('update', $this->service, $obj, $data, $this->controller_type);
        }

        $data = LuModels::fromData('update', $this->service, $obj, $data, $this->controller_type);

        $record = self::recordOf($data, $obj);

        return Result::ok('Record updated', $record, $record, encrypt($record->getKey()));
    }

    public function destroy($id)
    {
        // check if auth user owns the record
        // if not abort
        // if yes delete
        $obj = $this->findRecord($id);
        if (! $obj) {
            return Result::notFound();
        }
        // check if auth user owns the record
        // if not abort
        // if yes delete

        if ($this->dryRun()) {
            return LuModels::wouldDo('delete', $this->service, $obj, [], $this->controller_type);
        }

        $data = LuModels::fromData('delete', $this->service, $obj, request()->except(self::NOT_INPUT), $this->controller_type);

        $record = self::recordOf($data, $obj);

        return Result::ok('Record deleted', $record, $record);

    }
}
