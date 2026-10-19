<?php

namespace All1\LuModels\Http\Controllers\Api;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ApiRestController extends ApiResourceController
{
    /** @return mixed */
    public function __invoke(Request $request)
    {
        $id = $request->route('id');

        $this->prepare($request);
        $method = $request->method();
        if ($method == 'GET' || $method == 'HEAD') {
            return $this->chooseView($this->{$this->action}($id));
        } else {
            $result = $this->runAction($request, $id);

            if ($result->failed()) {
                return redirect()->back()->withErrors($result->errors)->withInput();
            }

            session()->flash('flash.banner', $result->message);

            return redirect()->back()->with('messages', [$result->message]);
        }
    }

    /** A browser form has nowhere to show a conditional: dry_run is ignored on this transport. */
    protected function supportsDryRun(): bool
    {
        return false;
    }

    /**
     * @param  mixed  $data
     * @return View
     */
    public function chooseView($data)
    {

        // if view file exists return view
        if (view()->exists(Str::plural($this->route_prefix).'.'.$this->action)) {
            return view(Str::plural($this->route_prefix).'.'.$this->action, ['data' => $data]);
        } else {
            return view('lu_models::web.'.$this->action, ['data' => $data])
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
}
