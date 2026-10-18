<?php

namespace All1\LuModels\Http\Controllers;

use All1\LuModels\Http\Controllers\Api\ApiResourceController;
use Illuminate\Http\Request;
use Str;

class ResourceController extends ApiResourceController
{
    public function __invoke(Request $request, $model = null, $action = 'index', $id = null)
    {

        $this->guardMethod($request);
        $method = $request->method();
        if ($method == 'GET' || $method == 'HEAD') {
            return $this->chooseView($this->{$this->action}($id));
        } else {

            $result = parent::__invoke($request, $model, $action, $id);
            // echo "Asda"; return false;

            // redirect back and flash message
            $redirect_action = config('lu::models.after_'.$this->action.'_redirect');
            if (isset($result['errors'])) {
                $popErrors = [];
                foreach ($result['errors']->errors()->all() as $error) {
                    $popErrors[] = json_encode([
                        'type' => 'warning',
                        'title' => 'Oops',
                        'message' => $error,
                    ]);
                }
                $toast_data = $popErrors;
                session()->put('toast', $toast_data);

                // dd(request()->input());
                return back()->withErrors($result['errors'])->withInput();
            }
            if (isset($result['message'])) {

                $toast_data = json_encode([
                    'type' => 'success',
                    'title' => 'Success',
                    'message' => $result['message'],
                ]);
                session()->put('toast', $toast_data);
                // dd("asdads");

                // if(config('lu_models::after_create_redirect')) {
                //   return redirect()->back()->with('messages', [$result['message']]);
                if ($redirect_action == 'back') {
                    return redirect()->back()->with('messages', [$result['message']]);
                } else {
                    return redirect()->route($this->redirectRouteName(), ['model' => $model, 'action' => $redirect_action, 'id' => $this->redirectId($result)])->with('messages', [$result['message']]);
                }
            } else {
                if ($redirect_action == 'back') {
                    return redirect()->back();
                } else {
                    return redirect()
                        ->route($this->redirectRouteName(), ['model' => $model, 'action' => $redirect_action, 'id' => $this->redirectId($result)]);
                }
            }

        }

    }

    /**
     * v3.0.1: the id to redirect to after a write. Store hands back an (encrypted) 'id'; update
     * and destroy hand back the record in 'data'. 3.0.0 read $result['data']->id, which raised
     * "Undefined property" (a 500) once the Verbs returned the service, and on a "Record not
     * found" result, where 'data' is an empty array.
     */
    protected function redirectId(array $result)
    {
        if (isset($result['id'])) {
            return $result['id'];
        }
        $record = self::recordOf($result['data'] ?? null);

        return $record?->getKey();
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
        elseif (view()->exists($this->model->viewFile($this->action))) {
            $view = view($this->model->viewFile($this->action), ['data' => $data]);
        } else {
            $view = view($this->model->viewFile($this->action), ['data' => $data]);
        }

        return $view
            ->with('model', $this->model)
            ->with('columns', $this->columns)
            ->with('filters', $this->filters)
            ->with('route_prefix', $this->route_prefix)
            ->with('meta', $this->meta)
            ->with('db_columns', $this->db_columns)
            ->with('controller_type', $this->controller_type)
            ->with('action', $this->action);

    }
}
