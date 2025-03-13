<?php

namespace All1\LuModels\Observers;

class UserOwnershipObserver
{

    public function creating($model) {   
        if( (auth()->user())) { 
            $model->user_id = auth()->user()->id;
        }elseif(env('APP_ENV')=='local' && strpos( $_SERVER['REQUEST_URI'], 'api/')===1) {  //as it starts with /api position is 1
            $model->user_id = 1;
        }

        return true;
    }
    /**
     * Handle the  "created" event.
     */
    public function created( $model): void
    {
        //
    }

    /**
     * Handle the  "updated" event.
     */
    public function updated( $model): void
    {
        //
    }

    /**
     * Handle the  "deleted" event.
     */
    public function deleted( $model): void
    {
        //
    }

    /**
     * Handle the  "restored" event.
     */
    public function restored( $model): void
    {
        //
    }

    /**
     * Handle the  "force deleted" event.
     */
    public function forceDeleted( $model): void
    {
        //
    }
}
