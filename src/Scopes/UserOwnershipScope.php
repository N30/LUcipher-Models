<?php

namespace All1\LuModels\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class UserOwnershipScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
       
        //get builder query from model
        //$model_table = $builder->query;
        
        //cosole and artisan commands should not be affected
        if(auth()->check()) {
            $user_id = auth()->id();
        }elseif(env('APP_ENV')=='local' && strpos( url()->current(), '/api/')!==false) {dd(strpos( url()->current(), 'api'));
            $user_id = 1;
        }
         
        if(app()->runningInConsole()) {
            return;
        }
        if(  isset($model->table)) { 
            $builder->where($model->table.'.user_id', $user_id);
            return;
        }else {
            $builder->where($model->getTable().'.user_id', $user_id);
            return ;
        }
    }
}
