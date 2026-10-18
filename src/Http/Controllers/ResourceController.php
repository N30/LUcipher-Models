<?php

namespace All1\LuModels\Http\Controllers;

use All1\LuModels\Http\Controllers\Api\ApiResourceController;
use All1\LuModels\Models\LuModel;
use All1\LuModels\Support\FieldVisibility;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ResourceController extends ApiResourceController
{
    public function __invoke(Request $request, $model = null, $action = 'index', $id = null)
    {

        $this->prepare($request);
        $method = $request->method();
        if ($method == 'GET' || $method == 'HEAD') {
            return $this->chooseView($this->{$this->action}($id));
        } else {

            $result = $this->runAction($request, $id);

            $redirect_action = config('lu::models.after_'.$this->action.'_redirect');

            if ($result->failed()) {
                $toasts = [];
                foreach ($result->errors->all() as $error) {
                    $toasts[] = json_encode(['type' => 'warning', 'title' => 'Oops', 'message' => $error]);
                }
                session()->put('toast', $toasts);

                return back()->withErrors($result->errors)->withInput();
            }

            session()->put('toast', json_encode(['type' => 'success', 'title' => 'Success', 'message' => $result->message]));

            if ($redirect_action == 'back') {
                return redirect()->back()->with('messages', [$result->message]);
            }

            return redirect()
                ->route($this->redirectRouteName(), ['model' => $model, 'action' => $redirect_action, 'id' => $result->redirectKey()])
                ->with('messages', [$result->message]);
        }
    }

    /**
     * v3.0.1: the redirect is followed with GET, so it is named after the transport's GET route
     * ("lu::web.resources"), not the write route that handled the request ("….update").
     */
    protected function redirectRouteName(): string
    {
        return preg_replace('/\.(store|update|destroy)$/', '', request()->route()->getName());
    }

    public function chooseView($data)
    {

        // if view file exists return view
        // sample: users.index
        if (view()->exists(Str::plural($this->route_prefix).'.'.$this->action)) {
            $view = view(Str::plural($this->route_prefix).'.'.$this->action, ['data' => $data]);
        }
        // sample: web.users.index
        elseif (view()->exists($this->controller_type.'.'.Str::plural($this->route_prefix).'.'.$this->action)) {
            $view = view($this->controller_type.'.'.Str::plural($this->route_prefix).'.'.$this->action, ['data' => $data]);
        }
        // custom
        // custom folder named after the model, else the package view (works for plain Eloquent models too)
        else {
            $view = view(LuModel::viewFileFor($this->model, $this->action), ['data' => $data]);
        }

        $columns = in_array($this->action, ['create', 'edit'], true)
            ? FieldVisibility::for($this->model)->filterColumns($this->columns, $this->action)
            : $this->columns;

        return $view
            ->with('model', $this->model)
            ->with('columns', $columns)
            ->with('filters', $this->filters)
            ->with('route_prefix', $this->route_prefix)
            ->with('meta', $this->meta)
            ->with('db_columns', $this->db_columns)
            ->with('controller_type', $this->controller_type)
            ->with('action', $this->action);

    }
}
