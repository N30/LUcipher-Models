<?php

namespace All1\LuModels\Http\Controllers\Api;

use Illuminate\Http\Request;

use All1\LuModels\Http\Controllers\Api\ApiResourceController;
use Str;

class ApiRestController extends ApiResourceController
{
    

    public function __invoke(Request $request, $model=null, $action='index', $id=null)
    {
        

        $method = $request->method();
        if($method=='GET') {
            return $this->chooseView( $this->{$this->action}($id));
        }else {
            $result = parent::__invoke($request, $model, $action, $id);
            //redirect back and flash message
            if(isset($result['message'])) { 
                session()->flash('flash.banner', $result['message']);
                return redirect()->back()->with('messages', [$result['message']]);
            }else {
                return redirect()->back();
            }
            
        }
         
 
    }

    
    public function chooseView( $data)
    {
         
        //if view file exists return view
        if(view()->exists(Str::plural($this->route_prefix).'.'.$this->action)) { 
            return view( Str::plural($this->route_prefix).'.'.$this->action , ['data' => $data]);
        } else { 
            return view('lu_models::web.'.$this->action ,  ['data'=> $data] )
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
