<?php

namespace All1\LuModels\Http\Controllers\Api;

use All1\LuModels\Routing\ResourceRoutes;
use All1\LuModels\Services\LuModels;
use All1\LuModels\Support\Envelope;
use All1\LuModels\Support\Keys;
use All1\LuModels\Support\Result;
use All1\LuModels\Support\Writer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

        return new JsonResponse(Envelope::of($result), 200);
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

    /** The record $id names (Support\Keys): encrypted, plain or slug; guests only where the model opts in. */
    protected function findRecord($id, bool $guestOptIns = false): ?Model
    {
        return Keys::find($this->model, $id, $guestOptIns);
    }

    /**
     * v3.0.1: the record a write action produced. The package Verbs return the service (its
     * ->model is the record); an app's own Action or Verb override may still return the model
     * itself. Anything else falls back to the model the controller looked up.
     */
    public static function recordOf($result, $fallback = null)
    {
        return Writer::recordOf($result, $fallback);
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
        $data ??= request()->except(self::NOT_INPUT);

        return Writer::create($this->model, $data, $this->controller_type, $this->dryRun());
    }

    public function update($id, $data = null)
    {
        $data ??= request()->except(self::NOT_INPUT);

        $record = $this->findRecord($id);
        if (! $record) {
            return Result::notFound();
        }

        return Writer::update($record, $data, $this->controller_type, $this->dryRun());
    }

    public function destroy($id)
    {
        $record = $this->findRecord($id);
        if (! $record) {
            return Result::notFound();
        }

        return Writer::delete($record, $this->controller_type, $this->dryRun());
    }
}
