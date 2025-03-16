<?php

namespace All1\LuModels\Http\Controllers;

use Illuminate\Http\Request;

use All1\LuModels\Http\Controllers\Api\ApiResourceController;
use Str;

class ResourceController extends ApiResourceController
{
    

    public function __invoke(Request $request, $model=null, $action='index', $id=null)
    {
        

        $method = $request->method();
        if($method=='GET') {
            return $this->chooseView( $this->{$this->action}($id));
        }else {
            $result = parent::__invoke($request, $model, $action, $id);
            //echo "Asda"; return false;
            //redirect back and flash message
            $redirect_action = config("lu::models.after_".$this->action."_redirect");
            
            if(isset($result['message'])) { 
                
                $toast_data = json_encode([
                    'type'=>'success',
                    'title'=>'Success',
                   'description'=>$result['message']
                ]);
                session()->put('toast', $toast_data);
                //dd("asdads");
                
               // if(config('lu_models::after_create_redirect')) {
                 //   return redirect()->back()->with('messages', [$result['message']]);
                if($redirect_action=='back'){ 
                    return redirect()->back()->with('messages', [$result['message']]);
                }else {
                    return redirect()->route( request()->route()->getName() ,[ 'model'=>$model, 'action'=>$redirect_action, 'id'=>$result['data']->id ])->with('messages', [$result['message']]);
                }
            }else {
                if($redirect_action=='back'){ 
                    return redirect()->back();
                }else {
                    return redirect()
                        ->route( request()->route()->getName() ,[ 'model'=>$model, 'action'=>$redirect_action, 'id'=>$result['data']->id ]);
                } 
            }
            
        }
         
 
    }

    public function chooseView( $data)
    {
         
        //if view file exists return view
        if(view()->exists(Str::plural($this->route_prefix).'.'.$this->action)) { 
            return view( Str::plural($this->route_prefix).'.'.$this->action , ['data' => $data]);
        } else { 
            return view($this->model->viewFile($this->action ),  ['data'=> $data] )
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
